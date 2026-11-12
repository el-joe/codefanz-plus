<?php

namespace App\Services\Assistant;

use App\Enums\AiFeature;
use App\Enums\AiUnavailableReason;
use App\Enums\BudgetRange;
use App\Enums\ChatMode;
use App\Enums\ChatRole;
use App\Enums\ProjectPlatform;
use App\Enums\ProjectTimeline;
use App\Exceptions\AiUnavailable;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiRequest;
use Illuminate\Support\Str;

/**
 * Runs AI Project Assistant conversations: the AI interview, and the deterministic guided questionnaire it
 * falls back to when AI is not configured, paused, over budget or failing. Both build the same brief.
 */
class ProjectAssistant
{
    public function __construct(
        private AiGateway $gateway,
        private BriefNormalizer $normalizer,
        private BriefProgress $progress,
        private SystemPrompt $prompt,
    ) {}

    /**
     * @return array{0: ChatSession, 1: string} the session and the browser's secret token
     */
    public function start(string $locale, ?string $ip, ?string $userAgent): array
    {
        $token = Str::random(48);
        $reason = $this->gateway->unavailableReason(AiFeature::ProjectAssistant);

        $session = ChatSession::create([
            'token_hash' => hash('sha256', $token),
            'locale' => $locale,
            'mode' => $reason === null ? ChatMode::Ai : ChatMode::Guided,
            'fallback_reason' => $reason?->value,
            'brief' => [],
            'ip_hash' => $ip !== null ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'last_activity_at' => now(),
        ]);

        $this->say($session, __("Hi! I'm the CodeFanz project assistant. I'll ask a few questions about your idea and prepare a project brief for our team. It's free — no payment needed."));

        if ($session->mode === ChatMode::Guided) {
            $this->say($session, $this->guidedQuestion('project'));
        } else {
            $this->say($session, __('What would you like to build?'));
        }

        return [$session, $token];
    }

    /**
     * One AI turn. Returns the new messages and the quick replies; switches to guided mode when AI is unavailable.
     *
     * @return array{messages: list<ChatMessage>, quick_replies: list<string>}
     */
    public function reply(ChatSession $session, string $text): array
    {
        $new = [$this->add($session, ChatRole::User, $text)];
        $session->increment('user_turns');

        $history = $session->messages()->latest('id')->limit((int) config('codefanz.assistant.history_messages'))->get()
            ->reverse()
            ->map(fn (ChatMessage $message): array => ['role' => $message->role->value, 'content' => $message->content])
            ->values()
            ->all();

        // The API expects the conversation to open with the visitor.
        while ($history !== [] && $history[0]['role'] !== ChatRole::User->value) {
            array_shift($history);
        }

        try {
            $response = $this->gateway->complete(
                AiFeature::ProjectAssistant,
                new AiRequest(
                    system: $this->prompt->build($session->locale),
                    messages: $history,
                    schema: $this->normalizer->schema(),
                    schemaName: 'project_brief_turn',
                    maxTokens: (int) config('codefanz.assistant.max_output_tokens'),
                ),
                ['chat_session_id' => $session->getKey()],
            );

            $reply = BriefNormalizer::cleanText($response->json['reply'] ?? null, 2000);

            if ($reply === null) {
                throw new AiUnavailable(AiUnavailableReason::Failed);
            }
        } catch (AiUnavailable $exception) {
            return ['messages' => [...$new, ...$this->switchToGuided($session, $exception->reason)], 'quick_replies' => []];
        }

        $updates = $this->normalizer->normalize(is_array($response->json['brief'] ?? null) ? $response->json['brief'] : []);
        $session->brief = $this->normalizer->merge($session->brief ?? [], $updates);
        $session->last_activity_at = now();
        $session->save();

        $new[] = $this->add($session, ChatRole::Assistant, $reply);

        $quickReplies = collect(is_array($response->json['quick_replies'] ?? null) ? $response->json['quick_replies'] : [])
            ->map(fn ($option): ?string => BriefNormalizer::cleanText($option, 60))
            ->filter()
            ->unique()
            ->take(4)
            ->values()
            ->all();

        return ['messages' => $new, 'quick_replies' => $quickReplies];
    }

    /**
     * One guided step (validated by the controller). Records the question and answer in the transcript.
     *
     * @param  array<string, mixed>  $input
     * @return list<ChatMessage>
     */
    public function answer(ChatSession $session, string $step, array $input): array
    {
        $updates = $this->normalizer->normalize($input);
        $brief = $this->normalizer->merge($session->brief ?? [], $updates);
        $brief['answered_steps'] = array_values(array_unique([...($brief['answered_steps'] ?? []), $step]));

        $session->forceFill(['brief' => $brief, 'last_activity_at' => now()])->save();

        $new = [$this->add($session, ChatRole::User, $this->describeAnswer($step, $updates))];
        $next = $this->nextGuidedStep($session);

        if ($next !== null) {
            $new[] = $this->say($session, $this->guidedQuestion($next));
        } else {
            $new[] = $this->say($session, __("Thanks! Here's what I understood — please check it, add your contact details and confirm."));
        }

        return $new;
    }

    public function nextGuidedStep(ChatSession $session): ?string
    {
        return $this->progress->nextGuidedStep($session->brief ?? [], $session->brief['answered_steps'] ?? []);
    }

    /**
     * @return list<ChatMessage>
     */
    public function switchToGuided(ChatSession $session, AiUnavailableReason $reason): array
    {
        $session->forceFill(['mode' => ChatMode::Guided, 'fallback_reason' => $reason->value, 'last_activity_at' => now()])->save();

        $new = [$this->say($session, self::fallbackNotice($reason))];
        $next = $this->nextGuidedStep($session);

        if ($next !== null) {
            $new[] = $this->say($session, $this->guidedQuestion($next));
        }

        return $new;
    }

    public static function fallbackNotice(?AiUnavailableReason $reason): string
    {
        return $reason === AiUnavailableReason::Failed
            ? __('Our AI assistant did not respond, so we switched to a short guided questionnaire. Your answers still reach our team.')
            : __('Our AI assistant is not available right now, so we switched to a short guided questionnaire. Your answers still reach our team.');
    }

    public function guidedQuestion(string $step): string
    {
        return match ($step) {
            'project' => __('What would you like to build? Give it a short name and tell us about your business.'),
            'platforms' => __('Which platforms do you need?'),
            'features' => __('What are the key features? Write one per line.'),
            'timeline' => __('When would you like it delivered?'),
            'budget' => __('Do you have a budget range in mind? This is optional.'),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function describeAnswer(string $step, array $updates): string
    {
        $text = match ($step) {
            'project' => trim(($updates['project_type'] ?? '')."\n".($updates['description'] ?? '')),
            'platforms' => collect($updates['platforms'] ?? [])->map(fn (string $value) => ProjectPlatform::tryFrom($value)?->label())->filter()->implode(', '),
            'features' => implode("\n", $updates['features'] ?? []),
            'timeline' => ProjectTimeline::tryFrom((string) ($updates['timeline'] ?? ''))?->label() ?? '',
            'budget' => BudgetRange::tryFrom((string) ($updates['budget_range'] ?? ''))?->label() ?? __('Skipped'),
            default => '',
        };

        return $text !== '' ? $text : __('Skipped');
    }

    private function say(ChatSession $session, string $text): ChatMessage
    {
        return $this->add($session, ChatRole::Assistant, $text);
    }

    private function add(ChatSession $session, ChatRole $role, string $content): ChatMessage
    {
        return $session->messages()->create(['role' => $role, 'content' => $content, 'created_at' => now()]);
    }
}
