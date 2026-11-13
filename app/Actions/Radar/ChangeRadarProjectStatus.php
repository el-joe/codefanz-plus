<?php

namespace App\Actions\Radar;

use App\Enums\RadarProjectStatus;
use App\Models\RadarProject;
use App\Models\User;
use App\Services\AuditLogger;

class ChangeRadarProjectStatus
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(RadarProject $project, RadarProjectStatus $status, User $user): RadarProject
    {
        $before = $project->status;

        if ($before === $status) {
            return $project;
        }

        $project->forceFill(['status' => $status, 'status_changed_by' => $user->getKey()])->save();

        $this->audit->record(operation: 'radar.status_changed', auditable: $project, user: $user, before: ['status' => $before->value], after: ['status' => $status->value]);

        return $project;
    }
}
