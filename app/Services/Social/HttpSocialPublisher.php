<?php

namespace App\Services\Social;

use App\Contracts\SocialPublisher;
use App\Exceptions\SocialApiException;
use App\Models\Media;
use App\Models\SocialPostVariant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Shared plumbing for the platform drivers: timeouts, error classification and media access.
 */
abstract class HttpSocialPublisher implements SocialPublisher
{
    protected function http(): PendingRequest
    {
        return Http::timeout((int) config('codefanz.social.timeout_seconds', 30))
            ->connectTimeout((int) config('codefanz.social.connect_timeout_seconds', 5))
            ->acceptJson();
    }

    /**
     * Run a request and turn every failure into a classified SocialApiException.
     *
     * @param  callable(): Response  $call
     */
    protected function call(callable $call, string $what): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException $exception) {
            throw self::connectionFailure($exception, $what);
        }

        if ($response->failed()) {
            throw $this->classify($response, $what);
        }

        return $response;
    }

    /**
     * Connection failures before the request reached the platform are safe to retry; a timeout while waiting for
     * the answer is not (the post may exist), so it is reported as "unknown".
     */
    public static function connectionFailure(ConnectionException $exception, string $what): SocialApiException
    {
        $message = $exception->getMessage();
        $neverSent = preg_match('/cURL error (6|7)\b|Could not resolve|Failed to connect|Connection refused|Connection timed out/i', $message) === 1;

        return new SocialApiException(
            $neverSent ? __(':what: the platform could not be reached.', ['what' => $what]) : __(':what: no answer from the platform; the post may or may not exist.', ['what' => $what]),
            $neverSent ? SocialApiException::TRANSIENT : SocialApiException::UNKNOWN,
        );
    }

    protected function classify(Response $response, string $what): SocialApiException
    {
        $status = $response->status();
        $detail = $this->errorDetail($response);
        $message = trim($what.' (HTTP '.$status.')'.($detail !== '' ? ': '.$detail : ''));

        return new SocialApiException($message, match (true) {
            $status === 429, $status >= 500 => SocialApiException::TRANSIENT,
            $status === 401 => SocialApiException::AUTH,
            $status === 403 => SocialApiException::APP_APPROVAL,
            default => SocialApiException::PERMANENT,
        }, $status);
    }

    protected function errorDetail(Response $response): string
    {
        $json = $response->json();
        $detail = is_array($json) ? ($json['message'] ?? $json['detail'] ?? $json['error']['message'] ?? $json['title'] ?? '') : '';

        return mb_substr(is_string($detail) ? $detail : '', 0, 300);
    }

    /**
     * Original bytes of a library image (for platforms that take uploads).
     */
    protected function mediaContents(Media $media): string
    {
        $contents = Storage::disk($media->original_disk)->get($media->original_path);

        if (! is_string($contents) || $contents === '') {
            throw new SocialApiException(__('The image “:name” could not be read from storage.', ['name' => $media->original_name]));
        }

        return $contents;
    }

    /**
     * A temporary signed URL to the original image (for platforms that fetch media by URL). It only works for
     * images attached to a social post and expires after an hour.
     */
    protected function mediaUrl(Media $media): string
    {
        return URL::temporarySignedRoute('social.media', now()->addHour(), ['media' => $media->uuid]);
    }

    protected function altText(SocialPostVariant $variant, Media $media): string
    {
        $alt = $variant->post->altFor($media->id);

        return mb_substr($alt !== '' ? $alt : $media->altText($variant->post->locale), 0, 1000);
    }

    /**
     * Remember an intermediate id on the variant right away so a retry reuses it.
     */
    protected function remember(SocialPostVariant $variant, string $key, mixed $value): void
    {
        $state = $variant->platform_state ?? [];
        data_set($state, $key, $value);
        $variant->forceFill(['platform_state' => $state])->save();
    }

    protected function remembered(SocialPostVariant $variant, string $key): mixed
    {
        return data_get($variant->platform_state ?? [], $key);
    }
}
