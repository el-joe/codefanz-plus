<?php

namespace App\Actions\Radar;

use App\Actions\Ai\GenerateContent;
use App\Enums\AiGenerationStatus;
use App\Enums\GuardrailFlagType;
use App\Enums\PromptPurpose;
use App\Enums\RadarProjectStatus;
use App\Models\PromptTemplate;
use App\Models\RadarProject;
use App\Models\RadarProposal;
use App\Models\User;
use App\Services\Ai\ContentGuardrails;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Drafts a proposal with the "radar_proposal" prompt template and the brand voice (GenerateContent → AiGateway, so
 * the ai_radar kill switch, budget and caps apply). The result is saved as a private draft for a person to copy and
 * edit; nothing is ever submitted to a platform. Guardrails flag prices (unless one was entered) and experience or
 * portfolio claims that are not in the facts the author supplied.
 */
class GenerateRadarProposal
{
    /**
     * Wording that claims past work; allowed only when the same words appear in the author's facts.
     */
    private const EXPERIENCE_PHRASES = [
        'portfolio', 'we have built', "we've built", 'we built', 'we have delivered', "we've delivered", 'years of experience',
        'similar project', 'our clients', 'case study', 'track record',
        'معرض أعمال', 'سابقة أعمال', 'سنوات من الخبرة', 'سنوات خبرة', 'عملاؤنا', 'قمنا ببناء', 'مشروع مماثل', 'مشاريع مماثلة',
    ];

    public function __construct(private GenerateContent $generate, private ContentGuardrails $guardrails, private AuditLogger $audit) {}

    public function handle(RadarProject $project, User $user, string $locale, ?string $facts = null, ?string $price = null): RadarProposal
    {
        $template = PromptTemplate::query()
            ->where('purpose', PromptPurpose::RadarProposal)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('locale')->orWhere('locale', $locale))
            ->orderByRaw('locale is null')
            ->orderBy('id')
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages(['ai' => __('There is no active freelance proposal template in the prompt library.')]);
        }

        $facts = trim((string) $facts);
        $price = trim((string) $price);

        $generation = $this->generate->handle($template, $user, [
            'project_title' => $project->title,
            'project_description' => mb_substr((string) $project->description, 0, 8000) ?: $project->title,
            'skills' => implode(', ', $project->skillList()),
            'our_facts' => $facts,
            'price' => $price,
        ], [$locale]);

        $body = trim((string) ($generation->output[$locale]['body'] ?? ''));
        $flags = $this->flags($body, (array) $generation->guardrail_flags, $facts, $price, $locale);

        $proposal = $project->proposals()->create([
            'locale' => $locale,
            'body' => $body,
            'guardrail_flags' => $flags,
            'ai_generation_id' => $generation->id,
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        $generation->forceFill(['status' => AiGenerationStatus::Saved])->save();

        if (in_array($project->status, [RadarProjectStatus::New, RadarProjectStatus::Shortlisted], true)) {
            $project->forceFill(['status' => RadarProjectStatus::ProposalDrafted, 'status_changed_by' => $user->getKey()])->save();
        }

        $this->audit->record(operation: 'radar.proposal_drafted', auditable: $project, user: $user, after: ['proposal_id' => $proposal->id, 'locale' => $locale, 'flags' => count($flags), 'template' => $template->key]);

        return $proposal;
    }

    /**
     * @param  list<array{type: string, locale: string, field: string, excerpt: string}>  $generated
     * @return list<array{type: string, locale: string, field: string, excerpt: string}>
     */
    public function flags(string $body, array $generated, string $facts, string $price, string $locale): array
    {
        $priceNumbers = $this->guardrails->numbers($price);

        // An entered price may appear in the draft; any other amount stays flagged.
        $flags = array_values(array_filter($generated, function (array $flag) use ($price, $priceNumbers): bool {
            if ($price === '' || ! in_array($flag['type'], [GuardrailFlagType::Price->value, GuardrailFlagType::UnverifiedNumber->value], true)) {
                return true;
            }

            $numbers = $this->guardrails->numbers($flag['excerpt']);

            return $numbers === [] || array_diff($numbers, $priceNumbers) !== [];
        }));

        foreach (self::EXPERIENCE_PHRASES as $phrase) {
            if (mb_stripos($body, $phrase) !== false && mb_stripos($facts, $phrase) === false) {
                $flags[] = ['type' => GuardrailFlagType::ExperienceClaim->value, 'locale' => $locale, 'field' => 'body', 'excerpt' => $phrase];
            }
        }

        return array_values(array_unique($flags, SORT_REGULAR));
    }
}
