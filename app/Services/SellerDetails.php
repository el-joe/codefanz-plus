<?php

namespace App\Services;

use App\Models\Language;

/**
 * The seller block on invoices, receipts and credit notes. snapshot() freezes the current business settings in
 * every active language when a document is issued; forLocale() picks the reader's language from a snapshot, so a
 * later change of company name or address never rewrites an issued document.
 */
class SellerDetails
{
    public function __construct(private Settings $settings) {}

    /**
     * @return array{name: array<string, string>, address: array<string, string>, email: ?string, phone: ?string}
     */
    public function snapshot(): array
    {
        $name = $this->settings->get('general', 'company_name');
        $address = $this->settings->get('contact', 'address');
        $email = $this->settings->get('contact', 'email');
        $phone = $this->settings->get('contact', 'phone');

        return [
            'name' => is_array($name) ? array_filter($name, fn ($value): bool => is_string($value) && $value !== '') : [],
            'address' => is_array($address) ? array_filter($address, fn ($value): bool => is_string($value) && $value !== '') : [],
            'email' => is_string($email) && $email !== '' ? $email : null,
            'phone' => is_string($phone) && $phone !== '' ? $phone : null,
        ];
    }

    /**
     * Display values in one language, from a frozen snapshot or (for drafts and legacy documents) live settings.
     *
     * @param  array<string, mixed>|null  $snapshot
     * @return array{name: string, address: ?string, email: ?string, phone: ?string}
     */
    public function forLocale(?array $snapshot, ?string $locale = null): array
    {
        $snapshot ??= $this->snapshot();
        $locale ??= app()->getLocale();
        $default = Language::defaultCode();
        $pick = fn (mixed $values): ?string => is_array($values) ? ($values[$locale] ?? $values[$default] ?? null) : null;

        return [
            'name' => $pick($snapshot['name'] ?? null) ?: 'CodeFanz',
            'address' => $pick($snapshot['address'] ?? null),
            'email' => $snapshot['email'] ?? null,
            'phone' => $snapshot['phone'] ?? null,
        ];
    }
}
