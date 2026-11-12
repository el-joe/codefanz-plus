<?php

namespace App\Services\Social;

/**
 * What the platform returned for a published post.
 */
final readonly class PublishResult
{
    /**
     * @param  array<string, mixed>  $response  trimmed platform response (ids and URLs only, never tokens)
     */
    public function __construct(
        public string $externalId,
        public ?string $url,
        public array $response = [],
    ) {}
}
