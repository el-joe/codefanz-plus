<?php

namespace App\Services\Radar;

use App\Exceptions\RadarConnectorException;
use App\Models\RadarProject;
use App\Services\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Telegram Bot API sendMessage with MarkdownV2. Every piece of project text is escaped, so listing text can never
 * break the formatting or inject links. The bot token lives encrypted in Settings → Radar and is never logged.
 */
class TelegramNotifier
{
    /**
     * Characters MarkdownV2 requires to be escaped outside entities.
     */
    private const SPECIAL = ['\\', '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

    public function __construct(private Settings $settings, private RadarScorer $scorer) {}

    public function isConfigured(): bool
    {
        return $this->settings->has('radar', 'telegram_bot_token') && $this->settings->has('radar', 'telegram_chat_id');
    }

    public static function escape(string $text): string
    {
        return str_replace(self::SPECIAL, array_map(fn (string $char): string => '\\'.$char, self::SPECIAL), $text);
    }

    /**
     * Inside the (...) part of an inline link only ")" and "\" must be escaped.
     */
    public static function escapeUrl(string $url): string
    {
        return str_replace(['\\', ')'], ['\\\\', '\\)'], $url);
    }

    /**
     * @throws RadarConnectorException
     */
    public function send(string $markdown): void
    {
        $token = $this->settings->get('radar', 'telegram_bot_token');
        $chat = $this->settings->get('radar', 'telegram_chat_id');

        if (! is_string($token) || $token === '' || ! is_string($chat) || $chat === '') {
            throw new RadarConnectorException(__('Telegram is not configured.'));
        }

        try {
            $response = Http::timeout((int) config('codefanz.radar.timeout_seconds', 15))
                ->acceptJson()
                ->post(rtrim((string) config('codefanz.radar.telegram.base_url'), '/').'/bot'.$token.'/sendMessage', [
                    'chat_id' => $chat,
                    'text' => mb_substr($markdown, 0, 4000),
                    'parse_mode' => 'MarkdownV2',
                    'disable_web_page_preview' => true,
                ]);
        } catch (ConnectionException) {
            throw new RadarConnectorException(__('Telegram could not be reached.'));
        }

        if ($response->failed() || $response->json('ok') !== true) {
            $description = (string) $response->json('description', '');

            throw new RadarConnectorException(trim(__('Telegram error (HTTP :status).', ['status' => $response->status()]).' '.mb_substr($description, 0, 200)), $response->status());
        }
    }

    public function projectMessage(RadarProject $project, string $levelLabel): string
    {
        $project->loadMissing(['platform', 'ruleEvaluation', 'aiEvaluation']);

        $lines = [
            '*'.self::escape($levelLabel).'* · '.self::escape((string) $project->platform?->name),
            '*'.self::escape($project->title).'*',
        ];

        $meta = array_filter([
            $project->budgetLabel() ?? __('Budget not stated'),
            $project->posted_at !== null ? __('Posted :time', ['time' => $project->posted_at->diffForHumans()]) : null,
            $project->proposals_count !== null ? __(':count proposals', ['count' => $project->proposals_count]) : null,
        ]);
        $lines[] = self::escape(implode(' · ', $meta));

        $score = __('Rule score :score/100', ['score' => (int) $project->score]);

        if ($project->ai_score !== null) {
            $score .= ' · '.__('AI :score/100', ['score' => $project->ai_score]);
        }

        $lines[] = self::escape($score);

        $skills = $this->scorer->matchingSkills($project);

        if ($skills !== []) {
            $lines[] = self::escape(__('Matching skills: :skills', ['skills' => implode(', ', array_slice($skills, 0, 8))]));
        }

        $lines[] = self::escape(__('Why: :reason', ['reason' => $this->why($project)]));
        $lines[] = '['.self::escape(__('Open project')).']('.self::escapeUrl($project->url).')';

        return implode("\n", $lines);
    }

    /**
     * @param  iterable<RadarProject>  $projects
     */
    public function digestMessage(iterable $projects, int $total): string
    {
        $lines = ['*'.self::escape(__('Freelance Radar daily digest')).'*', self::escape(__('Top :shown of :total new matches', ['shown' => is_countable($projects) ? count($projects) : 0, 'total' => $total]))];
        $position = 1;

        foreach ($projects as $project) {
            $lines[] = '';
            $lines[] = self::escape($position++.'. ').'['.self::escape($project->title).']('.self::escapeUrl($project->url).')';
            $lines[] = self::escape(implode(' · ', array_filter([
                (string) $project->platform?->name,
                $project->budgetLabel(),
                __('score :score', ['score' => (int) $project->score]).($project->ai_score !== null ? ' / AI '.$project->ai_score : ''),
            ])));
        }

        return implode("\n", $lines);
    }

    /**
     * The two strongest weighted factors, in plain words.
     */
    private function why(RadarProject $project): string
    {
        $breakdown = collect((array) $project->ruleEvaluation?->breakdown)
            ->sortByDesc(fn (array $factor): int => (int) $factor['score'] * (int) $factor['weight'])
            ->take(2)
            ->map(fn (array $factor): string => __($factor['reason'], (array) ($factor['params'] ?? [])));

        return $breakdown->implode('; ') ?: __('Rule score above the alert threshold');
    }
}
