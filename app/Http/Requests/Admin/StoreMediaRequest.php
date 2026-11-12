<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/avif',
                'max:'.config('codefanz.media.max_upload_kb'),
            ],
            'folder' => ['nullable', 'string', 'max:60', 'alpha_dash'],
        ];
    }

    /**
     * Reject images whose real dimensions are unreadable, oversized, or large enough to exhaust memory when decoded.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->file('files') as $index => $file) {
                    if (! $file instanceof UploadedFile || $validator->errors()->has("files.{$index}")) {
                        continue;
                    }

                    $info = @getimagesize($file->getRealPath());

                    if ($info === false) {
                        $validator->errors()->add("files.{$index}", __('This file is not a readable image.'));

                        continue;
                    }

                    [$width, $height] = $info;
                    $maxDimension = config('codefanz.media.max_dimension');

                    if ($width > $maxDimension || $height > $maxDimension || $width * $height > config('codefanz.media.max_pixels')) {
                        $validator->errors()->add("files.{$index}", __('Images must be at most :max pixels on each side.', ['max' => $maxDimension]));
                    }
                }
            },
        ];
    }
}
