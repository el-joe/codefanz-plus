<?php

namespace App\Console\Commands;

use App\Enums\PaymentLinkStatus;
use App\Models\PaymentLink;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:expire-links')]
#[Description('Mark active payment links past their expiry date as expired')]
class ExpirePaymentLinksCommand extends Command
{
    public function handle(): int
    {
        $count = PaymentLink::query()
            ->where('status', PaymentLinkStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => PaymentLinkStatus::Expired, 'updated_at' => now()]);

        $this->info("Expired {$count} payment link(s).");

        return self::SUCCESS;
    }
}
