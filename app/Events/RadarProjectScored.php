<?php

namespace App\Events;

use App\Models\RadarProject;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A radar project received a new rule score (new or changed listing, or new weights). Hook point for n8n.
 */
class RadarProjectScored
{
    use Dispatchable, SerializesModels;

    public function __construct(public RadarProject $project) {}
}
