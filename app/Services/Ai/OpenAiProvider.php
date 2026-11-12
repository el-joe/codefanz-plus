<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;

/**
 * OpenAI Chat Completions adapter (structured output through response_format json_schema, strict mode).
 */
class OpenAiProvider extends HttpAiProvider
{
    public function name(): string
    {
        return 'openai';
    }

    public function chat(AiRequest $request): AiResponse
    {
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ...array_map(fn (array $message): array => ['role' => $message['role'], 'content' => $message['content']], $request->messages),
            ],
            'max_completion_tokens' => $request->maxTokens,
        ];

        if ($request->schema !== null) {
            $body['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => ['name' => $request->schemaName, 'strict' => true, 'schema' => $request->schema],
            ];
        }

        $response = $this->post(
            rtrim((string) config('codefanz.ai.openai.base_url'), '/').'/chat/completions',
            ['Authorization' => 'Bearer '.$this->apiKey],
            $body,
        );

        $message = $response->json('choices.0.message');

        if (! is_array($message)) {
            throw new AiProviderException('Unexpected response shape');
        }

        if (filled($message['refusal'] ?? null)) {
            throw new AiProviderException('The model refused the request');
        }

        $text = (string) ($message['content'] ?? '');

        return new AiResponse(
            text: $text,
            json: $request->schema !== null ? $this->decodeJson($text) : null,
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            model: (string) ($response->json('model') ?? $this->model),
        );
    }
}
