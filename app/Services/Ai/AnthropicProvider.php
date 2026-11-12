<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;

/**
 * Anthropic Messages API adapter (structured output through output_config.format json_schema).
 */
class AnthropicProvider extends HttpAiProvider
{
    public function name(): string
    {
        return 'anthropic';
    }

    public function chat(AiRequest $request): AiResponse
    {
        $outputConfig = array_filter([
            'effort' => config('codefanz.ai.anthropic.effort') ?: null,
            'format' => $request->schema !== null ? ['type' => 'json_schema', 'schema' => $request->schema] : null,
        ]);

        $body = array_filter([
            'model' => $this->model,
            'max_tokens' => $request->maxTokens,
            'system' => $request->system,
            'messages' => array_map(fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']], $request->messages),
            'output_config' => $outputConfig ?: null,
        ], fn ($value): bool => $value !== null);

        $response = $this->post(
            rtrim((string) config('codefanz.ai.anthropic.base_url'), '/').'/v1/messages',
            [
                'x-api-key' => (string) $this->apiKey,
                'anthropic-version' => (string) config('codefanz.ai.anthropic.version'),
            ],
            $body,
        );

        if ($response->json('stop_reason') === 'refusal') {
            throw new AiProviderException('The model refused the request');
        }

        $text = collect((array) $response->json('content', []))
            ->filter(fn ($block): bool => is_array($block) && ($block['type'] ?? null) === 'text')
            ->pluck('text')
            ->implode('');

        if ($text === '') {
            throw new AiProviderException('Unexpected response shape');
        }

        return new AiResponse(
            text: $text,
            json: $request->schema !== null ? $this->decodeJson($text) : null,
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            model: (string) ($response->json('model') ?? $this->model),
        );
    }
}
