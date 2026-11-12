<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gap-free document numbers (INV-2026-00001, RCP-2026-00001). The counter row is locked with SELECT … FOR UPDATE
 * and incremented inside the caller's transaction, so a rolled-back issue never consumes a number and two
 * concurrent issues can never share one.
 */
class DocumentNumbers
{
    public const INVOICE = 'invoice';

    public const RECEIPT = 'receipt';

    /**
     * @var array<string, string>
     */
    private const PREFIXES = [self::INVOICE => 'INV', self::RECEIPT => 'RCP'];

    public function next(string $type, ?int $year = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Document numbers must be taken inside the transaction that stores the document.');
        }

        $prefix = self::PREFIXES[$type] ?? throw new LogicException("Unknown document type [{$type}].");
        $year ??= (int) now()->year;

        // Create the counter once per year; insertOrIgnore keeps concurrent first issues from failing.
        DocumentSequence::query()->insertOrIgnore(['type' => $type, 'year' => $year, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $sequence = DocumentSequence::query()->where('type', $type)->where('year', $year)->lockForUpdate()->firstOrFail();
        $value = $sequence->next_value;

        $sequence->forceFill(['next_value' => $value + 1])->save();

        return sprintf('%s-%d-%05d', $prefix, $year, $value);
    }
}
