<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared HTTP plumbing for provider adapters: timeouts, and retries with exponential backoff on 429, 5xx
 * and connection errors only (a 400/401 will not get better by asking again).
 */
abstract class HttpAiProvider implements AiProvider
{
    public function __construct(protected ?string $apiKey, protected string $model) {}

    public function model(): string
    {
        return $this->model;
    }

    public function isConfigured(): bool
    {
        return is_string($this->apiKey) && $this->apiKey !== '' && $this->model !== '';
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    protected function post(string $url, array $headers, array $body): Response
    {
        $config = config('codefanz.ai');

        try {
            return $this->client($headers, $config)->post($url, $body)->throw();
        } catch (RequestException $exception) {
            throw new AiProviderException('HTTP '.$exception->response->status(), previous: $exception);
        } catch (ConnectionException $exception) {
            throw new AiProviderException('Connection failed or timed out', previous: $exception);
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $config
     */
    private function client(array $headers, array $config): PendingRequest
    {
        $base = (int) $config['retry_base_ms'];

        return Http::acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->timeout((int) $config['timeout_seconds'])
            ->connectTimeout((int) $config['connect_timeout_seconds'])
            ->retry(
                (int) $config['retries'] + 1,
                fn (int $attempt): int => $base * (2 ** ($attempt - 1)),
                fn (Throwable $exception): bool => self::isRetryable($exception),
            );
    }

    public static function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->status() === 429 || $exception->response->serverError());
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(string $text): array
    {
        // Some models wrap JSON in a Markdown fence even when asked not to.
        $text = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new AiProviderException('Invalid JSON in the structured response');
        }

        return $decoded;
    }
}
