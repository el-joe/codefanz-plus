<?php

namespace App\Http\Controllers;

use App\Actions\Assistant\SubmitAssistantBrief;
use App\Enums\AiFeature;
use App\Enums\AiUnavailableReason;
use App\Enums\ChatMode;
use App\Http\Requests\Assistant\AssistantAnswerRequest;
use App\Http\Requests\Assistant\AssistantMessageRequest;
use App\Http\Requests\Assistant\ConfirmAssistantBriefRequest;
use App\Http\Requests\Assistant\StartAssistantRequest;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Service;
use App\Services\Ai\AiGateway;
use App\Services\Assistant\BriefProgress;
use App\Services\Assistant\ProjectAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * AI Project Assistant (Figma "15 · AI Project Assistant"): a free chat that interviews the visitor and turns
 * the confirmed brief into a CRM lead, or a guided questionnaire when AI is unavailable. JSON endpoints are
 * called with fetch; each conversation is bound to the browser by a secret token (X-Assistant-Token).
 */
class AssistantController extends Controller
{
    public function __construct(private ProjectAssistant $assistant, private BriefProgress $progress) {}

    public function show(AiGateway $gateway): View
    {
        $reason = $gateway->unavailableReason(AiFeature::ProjectAssistant);

        return view('assistant.show', [
            'aiAvailable' => $reason === null,
            'formToken' => StartAssistantRequest::timestampToken(),
            'services' => Service::query()->published()->translatedIn()->with('translations')->ordered()->get(),
            'progress' => $this->progress->progress([]),
        ]);
    }

    public function start(StartAssistantRequest $request): JsonResponse
    {
        if ($request->isBot()) {
            Log::notice('Assistant conversation discarded by the spam checks.', ['ip' => $request->ip()]);

            return response()->json(['message' => __('We could not start the conversation. Please reload the page and try again.')], 422);
        }

        if ($request->isExpired()) {
            return response()->json(['message' => __('This page has expired. Please reload it to start a new conversation.')], 422);
        }

        [$session, $token] = $this->assistant->start(app()->getLocale(), $request->ip(), $request->userAgent());

        return response()->json([
            ...$this->state($session, $session->messages()->get()->all()),
            'session' => $session->ulid,
            'token' => $token,
        ], 201);
    }

    public function message(AssistantMessageRequest $request, ChatSession $chatSession): JsonResponse
    {
        $this->guard($request, $chatSession);

        if ($chatSession->mode === ChatMode::Guided) {
            return response()->json($this->state($chatSession, []), 409);
        }

        if ($chatSession->user_turns >= (int) config('codefanz.assistant.max_turns')) {
            return response()->json([
                ...$this->state($chatSession, []),
                'message' => __('This conversation has reached its limit. Please review the summary and confirm, or use the quote form.'),
                'limit_reached' => true,
            ], 429);
        }

        $text = $request->text();

        if ($text === '') {
            return response()->json(['message' => __('Please type a message.'), 'errors' => ['message' => [__('Please type a message.')]]], 422);
        }

        $result = $this->assistant->reply($chatSession, $text);

        return response()->json([
            ...$this->state($chatSession->refresh(), $result['messages']),
            'quick_replies' => $result['quick_replies'],
        ]);
    }

    public function answer(AssistantAnswerRequest $request, ChatSession $chatSession): JsonResponse
    {
        $this->guard($request, $chatSession);

        $messages = $this->assistant->answer($chatSession, $request->validated('step'), $request->answers());

        return response()->json($this->state($chatSession->refresh(), $messages));
    }

    public function confirm(ConfirmAssistantBriefRequest $request, ChatSession $chatSession, SubmitAssistantBrief $submit): JsonResponse
    {
        $this->guard($request, $chatSession);

        if (! $this->progress->isReady($chatSession->brief ?? [])) {
            return response()->json(['message' => __('A few project details are still missing. Please answer the remaining questions first.')], 422);
        }

        $lead = $submit->handle($chatSession, $request->contact(), $request->ip(), $request->userAgent());

        return response()->json([
            'reference' => $lead->reference,
            'message' => __('Thank you! Your brief is on its way. Your reference number is :reference. We will reply within one business day.', ['reference' => $lead->reference]),
        ]);
    }

    /**
     * Only the browser that started the conversation may continue it, and only while it is open.
     */
    private function guard(Request $request, ChatSession $chatSession): void
    {
        abort_unless($chatSession->matchesToken($request->header('X-Assistant-Token')), 404);
        abort_unless($chatSession->isOpen(), 410, __('This conversation has ended. Please start a new one.'));
    }

    /**
     * Everything the page needs after an action. Messages are plain text; the browser renders them with textContent.
     *
     * @param  list<ChatMessage>  $messages
     * @return array<string, mixed>
     */
    private function state(ChatSession $session, array $messages): array
    {
        $brief = $session->brief ?? [];
        $guided = $session->mode === ChatMode::Guided;
        $step = $guided ? $this->assistant->nextGuidedStep($session) : null;
        $ready = $this->progress->isReady($brief) && $step === null;

        return [
            'mode' => $session->mode->value,
            'notice' => $guided ? ProjectAssistant::fallbackNotice(AiUnavailableReason::tryFrom((string) $session->fallback_reason)) : null,
            'messages' => array_map(fn (ChatMessage $message): array => [
                'role' => $message->role->value,
                'content' => $message->content,
                'time' => $message->created_at?->format('H:i'),
            ], $messages),
            'quick_replies' => [],
            'progress' => $this->progress->progress($brief),
            'step' => $step,
            'summary' => $ready ? [
                'rows' => $this->progress->summary($brief),
                'contact' => $this->progress->contact($brief, $session->locale),
            ] : null,
        ];
    }
}
