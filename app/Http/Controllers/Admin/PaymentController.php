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
use App\Services\Money;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'gateway' => ['nullable', Rule::in($gateways->keys())],
        ]);

        $payments = Payment::query()
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
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'filters' => $filters,
            'gatewayOptions' => $gateways->labels(),
            'kpis' => $this->kpis(),
        ]);
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load([
            'customer:id,ulid,company_name', 'project:id,ulid,name,reference', 'milestone:id,title',
            'link:id,ulid,reference,description,status,expires_at', 'receipt', 'events.user:id,name',
        ]);

        return view('admin.payments.show', ['payment' => $payment]);
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
