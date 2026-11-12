<?php

namespace App\Http\Requests\Concerns;

/**
 * File rules shared by lead attachments and project documents: `mimes` checks the detected content type,
 * `extensions` the file name; both must match the allow-list in codefanz.leads.attachments.
 */
trait ValidatesUploadedFiles
{
    /**
     * @return list<string>
     */
    protected function uploadedFileRules(): array
    {
        $attachments = config('codefanz.leads.attachments');
        $extensions = implode(',', $attachments['extensions']);

        return ['file', 'mimes:'.$extensions, 'extensions:'.$extensions, 'max:'.$attachments['max_kb']];
    }
}
