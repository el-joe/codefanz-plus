<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\CancelPaymentLink;
use App\Actions\Payments\CreatePaymentLink;
use App\Enums\PaymentLinkStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentLinkRequest;
use App\Models\PaymentLink;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Payment links: list (A05 tab), create from a project (A04 dialog) and cancel. Links are never deleted.
 */
class PaymentLinkController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PaymentLink::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PaymentLinkStatus::class)],
        ]);

        $links = PaymentLink::query()
            ->with(['customer:id,ulid,company_name', 'project:id,ulid,name,reference', 'milestone:id,title', 'creator:id,name'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('reference', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('company_name', 'like', $like)));
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                match (PaymentLinkStatus::from($status)) {
                    PaymentLinkStatus::Active => $query->open(),
                    PaymentLinkStatus::Expired => $query->where(fn (Builder $query) => $query->where('status', PaymentLinkStatus::Expired)
                        ->orWhere(fn (Builder $query) => $query->where('status', PaymentLinkStatus::Active)->where('expires_at', '<=', now()))),
                    default => $query->where('status', $status),
                };
            })
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments.links', ['links' => $links, 'filters' => $filters]);
    }

    public function store(StorePaymentLinkRequest $request, Project $project, CreatePaymentLink $create): RedirectResponse
    {
        $link = $create->handle($project, $request->linkData(), $request->user());

        return redirect()->route('admin.projects.show', ['project' => $project, 'tab' => 'payments'])
            ->with('toast', __('Payment link :reference created.', ['reference' => $link->reference]))
            ->with('createdPaymentLink', $link->publicUrl());
    }

    public function cancel(Request $request, PaymentLink $paymentLink, CancelPaymentLink $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $paymentLink);

        $cancel->handle($paymentLink, $request->user());

        return back()->with('toast', __('Payment link :reference cancelled.', ['reference' => $paymentLink->reference]));
    }
}
