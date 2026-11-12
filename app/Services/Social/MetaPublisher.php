<?php

namespace App\Services\Social;

use App\Exceptions\SocialApiException;
use Illuminate\Http\Client\Response;

/**
 * Graph API plumbing shared by Facebook Pages and Instagram (both publish with the page access token).
 */
abstract class MetaPublisher extends HttpSocialPublisher
{
    protected function graph(string $path): string
    {
        return rtrim((string) config('codefanz.social.providers.meta.graph_url'), '/').'/'.config('codefanz.social.providers.meta.version').'/'.ltrim($path, '/');
    }

    /**
     * Graph error codes: 190 invalid token; 10 and 200–299 missing permission (app review); 1, 2, 4, 17, 32, 341,
     * 613 and is_transient are temporary; 9007 = Instagram media not ready yet.
     */
    protected function classify(Response $response, string $what): SocialApiException
    {
        $error = (array) $response->json('error', []);
        $code = (int) ($error['code'] ?? 0);
        $detail = mb_substr((string) ($error['message'] ?? ''), 0, 300);
        $message = trim($what.' (HTTP '.$response->status().($code ? ', code '.$code : '').')'.($detail !== '' ? ': '.$detail : ''));

        $kind = match (true) {
            $code === 190, $response->status() === 401 => SocialApiException::AUTH,
            $code === 10, $code >= 200 && $code <= 299 => SocialApiException::APP_APPROVAL,
            in_array($code, [1, 2, 4, 17, 32, 341, 613, 9007], true), (bool) ($error['is_transient'] ?? false), $response->status() === 429, $response->serverError() => SocialApiException::TRANSIENT,
            default => SocialApiException::PERMANENT,
        };

        return new SocialApiException($message, $kind, $response->status());
    }
}
