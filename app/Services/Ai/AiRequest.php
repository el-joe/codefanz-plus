<?php

namespace App\Services\Ai;

/**
 * A provider-neutral chat request. Untrusted content (visitor messages, scraped text) belongs in $messages,
 * never in $system.
 */
final readonly class AiRequest
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     * @param  array<string, mixed>|null  $schema  JSON schema for structured output (strict: every property required)
     */
    public function __construct(
        public string $system,
        public array $messages,
        public ?array $schema = null,
        public string $schemaName = 'response',
        public int $maxTokens = 2000,
    ) {}
}
