<?php

namespace App\Contracts;

use App\Enums\Permission;

/**
 * A model whose public-facing content may only be changed by AI or MCP through an approved change request.
 */
interface Approvable
{
    /**
     * Current optimistic-lock version of the target.
     */
    public function approvableVersion(): int;

    /**
     * Attributes that a change request may modify.
     *
     * @return list<string>
     */
    public function approvableAttributes(): array;

    /**
     * Snapshot of the approvable attributes for the given locale.
     *
     * @return array<string, mixed>
     */
    public function approvableSnapshot(?string $locale): array;

    /**
     * Persist the approved attributes and bump the version. Called inside a transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyApprovedAttributes(array $attributes, ?string $locale): void;

    /**
     * Permission a reviewer needs to approve the given proposed attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function approvalPermission(array $attributes): Permission;
}
