<?php

namespace App\Actions\Seo;

use App\Models\Redirect;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Seo\RedirectPaths;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * CSV import: `from,to,status` per line (header row optional, status defaults to 301). Each row goes through the
 * same normalization and loop/chain checks as the form; invalid rows are reported by line and skipped, existing
 * sources are updated.
 */
class ImportRedirects
{
    public const MAX_ROWS = 1000;

    public function __construct(private SaveRedirect $save, private AuditLogger $audit) {}

    /**
     * @return array{imported: int, errors: list<string>}
     */
    public function handle(UploadedFile $file, User $editor): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $imported = 0;
        $errors = [];
        $line = 0;

        while (($row = fgetcsv($handle, 2000, ',', '"', '')) !== false) {
            $line++;

            if ($row === [null] || $row === []) {
                continue;
            }

            $from = trim((string) ($row[0] ?? ''));
            $to = trim((string) ($row[1] ?? ''));
            $code = trim((string) ($row[2] ?? '')) ?: '301';

            if ($line === 1 && in_array(strtolower($from), ['from', 'from_path', 'source'], true)) {
                continue;
            }

            if ($line > self::MAX_ROWS + 1) {
                $errors[] = __('Only the first :count rows were read.', ['count' => self::MAX_ROWS]);

                break;
            }

            if (! in_array((int) $code, Redirect::STATUS_CODES, true)) {
                $errors[] = __('Line :line: status must be 301, 302 or 410.', ['line' => $line]);

                continue;
            }

            try {
                $existing = Redirect::query()->where('from_path', RedirectPaths::source($from) ?? '')->first();
                $this->save->handle($existing, ['from_path' => $from, 'to_path' => $to, 'status_code' => (int) $code, 'is_active' => true, 'note' => __('Imported from CSV')], $editor, audit: false);
                $imported++;
            } catch (ValidationException $exception) {
                $errors[] = __('Line :line: :error', ['line' => $line, 'error' => collect($exception->errors())->flatten()->first()]);
            }
        }

        fclose($handle);

        $this->audit->record(operation: 'redirect.imported', user: $editor, after: ['imported' => $imported, 'rejected' => count($errors)]);

        return ['imported' => $imported, 'errors' => array_slice($errors, 0, 50)];
    }
}
