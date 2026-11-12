<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\VerifyPayment;
use App\Enums\PaymentEventSource;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Concerns\RendersBillingDocuments;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\AuditLogger;
use App\Services\Money;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payments & transactions (A05): KPIs, the transactions ledger with gateway, fee, net and how each payment was
 * verified, a payment's event history, staff re-verification with the gateway, and receipts.
 */
class PaymentController extends Controller
{
    use RendersBillingDocuments;

    public function index(Request $request, PaymentGatewayManager $gateways): View
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $this->filters($request, $gateways);

        $payments = $this->filteredQuery($filters)
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'filters' => $filters,
            'gatewayOptions' => $gateways->labels(),
            'kpis' => $this->kpis(),
        ]);
    }

    /**
     * Streamed CSV of the filtered transactions. Text cells that a spreadsheet would run as a formula are prefixed
     * with an apostrophe (CSV injection), and the export is audited.
     */
    public function export(Request $request, PaymentGatewayManager $gateways, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('viewAny', Payment::class);

        $filters = $this->filters($request, $gateways);
        $audit->record(operation: 'payments.exported', user: $request->user(), after: ['filters' => $filters]);

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}");
            fputcsv($out, ['Reference', 'Date', 'Client', 'Project', 'Milestone', 'Gateway', 'Gateway reference', 'Status', 'Currency', 'Amount', 'Fee', 'Net', 'Refunded', 'Paid at', 'Verified via'], escape: '');

            foreach ($this->filteredQuery($filters)->reorder()->lazyById(500) as $payment) {
                fputcsv($out, array_map(self::csvCell(...), [
                    $payment->reference,
                    $payment->created_at?->toDateTimeString(),
                    $payment->customer?->company_name,
                    $payment->project?->name,
                    $payment->milestone?->title,
                    $payment->gatewayLabel(),
                    $payment->gateway_payment_id,
                    $payment->status->value,
                    $payment->currency,
                    Money::toDecimal($payment->amount_minor),
                    Money::toDecimal($payment->fee_minor),
                    Money::toDecimal($payment->net_minor),
                    Money::toDecimal($payment->refunded_minor),
                    $payment->paid_at?->toDateTimeString(),
                    $payment->verified_via?->value,
                ]), escape: '');
            }

            fclose($out);
        }, 'payments-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutralise spreadsheet formulas: cells starting with = + - @ tab or carriage return get a leading apostrophe.
     * Plain decimal amounts (always non-negative here) are left as numbers.
     */
    public static function csvCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        if (preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 && ! str_starts_with($text, '-')) {
            return $text;
        }

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load([
            'customer:id,ulid,company_name', 'project:id,ulid,name,reference', 'milestone:id,title',
            'link:id,ulid,reference,description,status,expires_at', 'receipt', 'events.user:id,name',
            'refunds.requester:id,name', 'refunds.decider:id,name', 'refunds.creditNote:id,ulid,refund_id,number',
        ]);

        return view('admin.payments.show', ['payment' => $payment, 'refundable' => $payment->refundableMinor()]);
    }

    /**
     * Ask the gateway again (e.g. a webhook never arrived). Goes through VerifyPayment like every other path.
     */
    public function verify(Request $request, Payment $payment, VerifyPayment $verifyPayment): RedirectResponse
    {
        Gate::authorize('verify', $payment);

        try {
            $payment = $verifyPayment->handle($payment, PaymentEventSource::Admin, actor: $request->user());
        } catch (PaymentGatewayException $exception) {
            return back()->with('toast', __('The gateway could not be reached (:error). Nothing changed.', ['error' => $exception->getMessage()]));
        }

        return back()->with('toast', __('Gateway status: :status.', ['status' => $payment->status->label()]));
    }

    public function receipt(Request $request, Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load('receipt');

        return $this->receiptDocument($request, $payment, route('admin.payments.show', $payment));
    }

    /**
     * @return array{q?: string, status?: string, gateway?: string}
     */
    private function filters(Request $request, PaymentGatewayManager $gateways): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'gateway' => ['nullable', Rule::in($gateways->keys())],
        ]);
    }

    /**
     * @param  array{q?: string, status?: string, gateway?: string}  $filters
     * @return Builder<Payment>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Payment::query()
            ->with(['customer:id,ulid,company_name', 'project:id,ulid,name,reference', 'milestone:id,title'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('reference', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('company_name', 'like', $like))
                    ->orWhereHas('project', fn (Builder $project) => $project->where('name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['gateway'] ?? null, fn (Builder $query, string $gateway) => $query->where('gateway', $gateway))
            ->latest()
            ->latest('id');
    }

    /**
     * Month-to-date figures per currency (amounts are never added across currencies).
     *
     * @return array{collected: string, fees: string, open_links: int, open_links_total: string, failed: int}
     */
    private function kpis(): array
    {
        $monthStart = now()->startOfMonth();

        $collected = Payment::query()->whereIn('status', PaymentStatus::collected())->where('paid_at', '>=', $monthStart)
            ->groupBy('currency')->selectRaw('currency, SUM(amount_minor - refunded_minor) as total, SUM(COALESCE(fee_minor, 0)) as fees')->get();

        $openLinks = PaymentLink::query()->open()->groupBy('currency')->selectRaw('currency, COUNT(*) as links, SUM(amount_minor) as total')->get();

        $format = fn ($rows, string $column): string => $rows->isEmpty()
            ? Money::plain(0, (string) config('codefanz.projects.default_currency', 'USD'))
            : $rows->map(fn ($row): string => Money::plain((int) $row->{$column}, $row->currency))->implode(' · ');

        return [
            'collected' => $format($collected, 'total'),
            'fees' => $format($collected, 'fees'),
            'open_links' => (int) $openLinks->sum('links'),
            'open_links_total' => $format($openLinks, 'total'),
            'failed' => Payment::query()->where('status', PaymentStatus::Failed)->where('updated_at', '>=', $monthStart)->count(),
        ];
    }
}
