<?php

namespace App\Console\Commands;

use App\Enums\SocialAccountStatus;
use App\Jobs\RefreshSocialAccountToken;
use App\Models\SocialAccount;
use App\Services\Social\SocialTokenRefresher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('social:refresh-tokens')]
#[Description('Queue token refreshes for social accounts whose access token expires soon')]
class RefreshSocialTokensCommand extends Command
{
    public function handle(SocialTokenRefresher $refresher): int
    {
        $count = 0;

        SocialAccount::query()
            ->where('status', SocialAccountStatus::Connected)
            ->whereNotNull('token_expires_at')
            ->get()
            ->filter(fn (SocialAccount $account): bool => $refresher->needsRefresh($account))
            ->each(function (SocialAccount $account) use (&$count): void {
                RefreshSocialAccountToken::dispatch($account->id);
                $count++;
            });

        $this->info("Queued {$count} token refresh(es).");

        return self::SUCCESS;
    }
}
