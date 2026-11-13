<?php

namespace App\Http\Resources\Mcp;

use App\Models\SeoIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One open issue from the latest SEO audit.
 *
 * @mixin SeoIssue
 */
class SeoIssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'category' => $this->category,
            'url' => $this->url,
            'locale' => $this->locale,
            'target' => $this->seoable_type ? ['type' => $this->seoable_type, 'id' => $this->seoable_id] : ($this->route_key ? ['route' => $this->route_key] : null),
            'details' => $this->details,
        ];
    }
}
