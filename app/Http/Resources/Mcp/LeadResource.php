<?php

namespace App\Http\Resources\Mcp;

use App\Models\Lead;
use App\Models\McpClient;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A lead for MCP with personal data minimised: contact details and free text only appear when an administrator
 * enabled "lead personal data" on the client.
 *
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $client = $request->user();
        $withPersonalData = $client instanceof McpClient && $client->allow_lead_pii;

        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'priority' => $this->priority->value,
            'country' => $this->country,
            'preferred_locale' => $this->preferred_locale,
            'budget_range' => $this->budget_range?->value,
            'timeline' => $this->timeline?->value,
            'services' => $this->services->map(fn (Service $service): ?string => $service->translation('en')?->title ?? $service->translations->first()?->title)->filter()->values()->all(),
            'assigned' => $this->assigned_to !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'personal_data_included' => $withPersonalData,
            ...($withPersonalData ? [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'company' => $this->company,
                'description' => $this->description === null ? null : Str::limit($this->description, 2000),
            ] : []),
        ];
    }
}
