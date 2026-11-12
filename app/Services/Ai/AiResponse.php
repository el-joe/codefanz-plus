<?php

namespace App\Services\Ai;

final readonly class AiResponse
{
    /**
     * @param  array<string, mixed>|null  $json  decoded structured output when a schema was requested
     */
    public function __construct(
        public string $text,
        public ?array $json,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
    ) {}
}
