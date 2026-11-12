<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToCustomer;
use App\Services\Money;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Customer invoice. Drafts are editable and carry no number; App\Actions\Billing\IssueInvoice assigns the next
 * gap-free number and freezes the customer snapshot. After that only the status columns (paid / void) change.
 */
#[Fillable([
    'customer_id', 'project_id', 'milestone_id', 'payment_link_id', 'subtotal_minor', 'tax_rate_bp', 'tax_minor',
    'total_minor', 'currency', 'notes', 'due_at', 'created_by',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToCustomer, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'tax_rate_bp' => 0,
        'subtotal_minor' => 0,
        'tax_minor' => 0,
        'total_minor' => 0,
    ];

    /**
     * Columns that may still change once an invoice is issued.
     *
     * @var list<string>
     */
    private const MUTABLE_AFTER_ISSUE = ['status', 'paid_at', 'voided_at', 'void_reason', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            $wasDraft = $invoice->getOriginal('status') === InvoiceStatus::Draft;

            if (! $wasDraft && array_diff(array_keys($invoice->getDirty()), self::MUTABLE_AFTER_ISSUE) !== []) {
                throw new LogicException('Issued invoices are immutable; void it and issue a new one.');
            }
        });

        static::deleting(function (Invoice $invoice): void {
            if ($invoice->status !== InvoiceStatus::Draft) {
                throw new LogicException('Issued invoices cannot be deleted.');
            }
        });
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'customer_snapshot' => 'array',
            'subtotal_minor' => 'integer',
            'tax_rate_bp' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Invoices the client may see (never drafts).
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeVisibleToClient(Builder $query): void
    {
        $query->whereIn('status', InvoiceStatus::visibleToClient());
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Milestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * @return BelongsTo<PaymentLink, $this>
     */
    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Issued && $this->due_at !== null && $this->due_at->endOfDay()->isPast();
    }

    public function displayNumber(): string
    {
        return $this->number ?? __('Draft');
    }

    public function formattedTotal(): string
    {
        return Money::localized($this->total_minor, $this->currency);
    }

    /**
     * Bill-to details: the frozen snapshot once issued, the live customer while still a draft
     * (requires `customer` loaded for drafts).
     *
     * @return array{name: string, contact: ?string, email: ?string, address: list<string>, country: ?string, tax_id: ?string}
     */
    public function billTo(): array
    {
        return $this->customer_snapshot ?? self::snapshotOf($this->customer);
    }

    /**
     * @return array{name: string, contact: ?string, email: ?string, address: list<string>, country: ?string, tax_id: ?string}
     */
    public static function snapshotOf(Customer $customer): array
    {
        return [
            'name' => $customer->company_name,
            'contact' => $customer->contact_name,
            'email' => $customer->email,
            'address' => $customer->billingAddressLines(),
            'country' => $customer->country,
            'tax_id' => $customer->tax_id,
        ];
    }

    /**
     * Recalculate subtotal, tax and total from the items (requires `items` loaded).
     */
    public function recalculate(): void
    {
        $subtotal = (int) $this->items->sum('total_minor');
        $tax = intdiv($subtotal * $this->tax_rate_bp + 5000, 10000);

        $this->forceFill(['subtotal_minor' => $subtotal, 'tax_minor' => $tax, 'total_minor' => $subtotal + $tax]);
    }
}
