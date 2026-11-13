<?php

namespace App\Console\Commands;

use App\Services\Radar\RadarAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('radar:digest')]
#[Description('Send the daily Freelance Radar digest (top projects in the digest score band) to Telegram')]
class RadarDigestCommand extends Command
{
    public function handle(RadarAlerts $alerts): int
    {
        $result = $alerts->sendDigest();

        if ($result['error'] !== null) {
            $this->warn('Digest not sent: '.$result['error']);

            return self::SUCCESS;
        }

        $this->info($result['sent'] ? "Digest sent with {$result['count']} projects." : 'Nothing to send.');

        return self::SUCCESS;
    }
}
