<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Exceptions\AiProviderException;

/**
 * Used when no provider credentials are saved. Never makes a network call.
 */
class NullAiProvider implements AiProvider
{
    public function __construct(private string $provider = 'none') {}

    public function name(): string
    {
        return $this->provider;
    }

    public function model(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function chat(AiRequest $request): AiResponse
    {
        throw new AiProviderException('No AI provider is configured');
    }
}
