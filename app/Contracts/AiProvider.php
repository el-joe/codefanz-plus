<?php

namespace App\Contracts;

use App\Exceptions\AiProviderException;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResponse;

/**
 * A chat-completion provider (OpenAI, Anthropic, …). Adapters only translate the request and response;
 * budgets, kill switches and usage logging live in App\Services\Ai\AiGateway, which every feature calls.
 */
interface AiProvider
{
    /**
     * Provider key stored in ai_usage_logs (e.g. "openai").
     */
    public function name(): string;

    public function model(): string;

    public function isConfigured(): bool;

    /**
     * @throws AiProviderException when the provider cannot answer (HTTP error, timeout, refusal, invalid JSON)
     */
    public function chat(AiRequest $request): AiResponse;
}
