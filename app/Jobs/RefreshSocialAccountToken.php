<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Services\Social\SocialTokenRefresher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refreshes one account's OAuth token before it expires. Transient platform errors are retried with backoff.
 */
class RefreshSocialAccountToken implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public int $accountId)
    {
        $this->onQueue('social');
    }

    public function uniqueId(): string
    {
        return (string) $this->accountId;
    }

    public function handle(SocialTokenRefresher $refresher): void
    {
        $account = SocialAccount::query()->find($this->accountId);

        if ($account !== null && $refresher->needsRefresh($account)) {
            $refresher->refresh($account);
        }
    }
}
