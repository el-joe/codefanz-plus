<?php

namespace App\Services\Radar\Parsers;

use App\Enums\RadarBudgetType;
use App\Services\Radar\RadarProjectData;
use App\Services\Radar\RadarText;
use Carbon\CarbonImmutable;

/**
 * Turns a saved-search alert email (sent by the platform to our mailbox, forwarded by n8n) into normalized
 * projects. Links to project pages split the email into one block per project: the link text is the title and the
 * block holds the budget and the excerpt. Only what the email contains is used; project pages are never fetched.
 */
abstract class AlertEmailParser
{
    /**
     * Regex matching a project URL; group 1 must capture the platform's project id.
     */
    abstract protected function urlPattern(): string;

    abstract protected function defaultCurrency(): string;

    /**
     * Sender domains that identify this platform's alert emails.
     *
     * @return list<string>
     */
    abstract public function senderDomains(): array;

    /**
     * @return list<RadarProjectData>
     */
    public function parse(string $subject, ?string $html, ?string $text, ?CarbonImmutable $receivedAt = null): array
    {
        $body = filled($html) ? $this->flattenHtml((string) $html) : $this->markTextLinks((string) $text);
        $segments = preg_split('/\[\[LINK:/u', $body) ?: [];
        $blocks = [];
        $current = null;

        // Segment 0 is the email header; every other segment starts with "url|title]]".
        foreach (array_slice($segments, 1) as $segment) {
            if (! preg_match('/^(.*?)\|(.*?)\]\](.*)$/su', $segment, $match)) {
                continue;
            }

            [$url, $linkText, $rest] = [trim($match[1]), RadarText::clean($match[2], 255), $match[3]];

            if (! preg_match($this->urlPattern(), $url, $id)) {
                // Skill / category links inside a project block stay; anything else (footer, settings) ends it.
                $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
                $ownLink = collect($this->senderDomains())->contains(fn (string $domain): bool => $host === $domain || str_ends_with($host, '.'.$domain));

                if ($current !== null && $ownLink && ! $this->isNoise($linkText) && ! preg_match('/unsubscribe|settings|alerts?|notifications?/i', $url)) {
                    $blocks[$current]['text'] .= ' '.$linkText.' '.$rest;
                } else {
                    $current = null;
                }

                continue;
            }

            $externalId = $current = $id[1];

            if (! isset($blocks[$externalId])) {
                $blocks[$externalId] = ['url' => $this->canonicalUrl($url), 'title' => '', 'text' => ''];
            }

            if ($blocks[$externalId]['title'] === '' && $this->isTitle($linkText)) {
                $blocks[$externalId]['title'] = $linkText;
            }

            $blocks[$externalId]['text'] .= "\n".$rest;
        }

        $projects = [];

        foreach ($blocks as $externalId => $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", RadarText::clean($block['text']))), 'filled'));
            $title = $block['title'] !== '' ? $block['title'] : ($lines[0] ?? '');

            if ($title === '') {
                continue;
            }

            [$min, $max, $currency, $type] = $this->budget($block['text']);
            $description = implode("\n", array_filter($lines, fn (string $line): bool => ! $this->isNoise($line) && $line !== $title));

            $projects[] = new RadarProjectData(
                externalId: (string) $externalId,
                url: $block['url'],
                title: mb_substr($title, 0, 255),
                description: mb_substr($description, 0, 4000) ?: null,
                budgetMinMinor: $min,
                budgetMaxMinor: $max,
                currency: $currency,
                budgetType: $type,
                skills: $this->skills($block['text']),
                proposalsCount: $this->proposals($block['text']),
                postedAt: $receivedAt,
                source: ['email' => ['subject' => mb_substr($subject, 0, 255)], 'block' => mb_substr(RadarText::clean($block['text']), 0, 4000)],
            );
        }

        return $projects;
    }

    protected function canonicalUrl(string $url): string
    {
        return (string) strtok(html_entity_decode($url), '?#');
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: string|null, 3: RadarBudgetType}
     */
    protected function budget(string $text): array
    {
        $text = RadarText::normalize(RadarText::clean($text));
        $symbols = ['$' => 'USD', '£' => 'GBP', '€' => 'EUR', 'usd' => 'USD', 'gbp' => 'GBP', 'eur' => 'EUR'];
        $currency = '(\$|£|€|usd|gbp|eur)';
        $amount = '(\d[\d,٬]*(?:\.\d+)?)';
        $hourly = preg_match('/(\/\s?(hr|hour)|per hour|hourly|بالساعة|في الساعة)/u', $text) === 1;
        $type = $hourly ? RadarBudgetType::Hourly : RadarBudgetType::Fixed;

        if (preg_match("/{$currency}\s?{$amount}\s*(?:-|–|to|إلى)\s*{$currency}?\s?{$amount}/u", $text, $m)) {
            return [RadarText::toMinor($m[2]), RadarText::toMinor($m[4]), $symbols[$m[1]], $type];
        }

        if (preg_match("/{$amount}\s?{$currency}\s*(?:-|–|to|إلى)\s*{$amount}\s?{$currency}?/u", $text, $m)) {
            return [RadarText::toMinor($m[1]), RadarText::toMinor($m[3]), $symbols[$m[2]], $type];
        }

        if (preg_match("/{$currency}\s?{$amount}/u", $text, $m)) {
            $value = RadarText::toMinor($m[2]);

            return [$value, $value, $symbols[$m[1]], $type];
        }

        if (preg_match("/(?:budget|الميزانية)\s*[:：]?\s*{$amount}(?:\s*(?:-|–|to|إلى)\s*{$amount})?/u", $text, $m)) {
            $min = RadarText::toMinor($m[1]);

            return [$min, isset($m[2]) ? RadarText::toMinor($m[2]) : $min, $this->defaultCurrency(), $type];
        }

        return [null, null, null, RadarBudgetType::Unknown];
    }

    /**
     * @return list<string>
     */
    protected function skills(string $text): array
    {
        if (! preg_match('/(?:skills|المهارات)\s*[:：]\s*([^\n]+)/iu', RadarText::clean($text), $m)) {
            return [];
        }

        return array_slice(array_values(array_filter(array_map('trim', preg_split('/[,،|•·]/u', $m[1]) ?: []), 'filled')), 0, 20);
    }

    protected function proposals(string $text): ?int
    {
        $text = RadarText::normalize(RadarText::clean($text));

        if (preg_match('/(\d+)\s*(?:proposals?|bids?|عروض|عرض)/u', $text, $m) || preg_match('/(?:proposals?|العروض)\s*[:：]\s*(\d+)/u', $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    protected function isTitle(string $text): bool
    {
        return mb_strlen($text) >= 4 && ! $this->isNoise($text);
    }

    /**
     * Button and footer lines that are not part of a project ("View project", "Unsubscribe", …).
     */
    protected function isNoise(string $line): bool
    {
        return preg_match('/^(view|see|open|bid|apply|send a proposal|unsubscribe|manage alerts?|عرض|شاهد|قدم عرضك|أضف عرضك|إلغاء الاشتراك)(\s.*)?$/iu', trim($line)) === 1;
    }

    private function flattenHtml(string $html): string
    {
        $html = (string) preg_replace('~<(script|style|head)\b[^>]*>.*?</\1>~is', ' ', $html);
        $html = (string) preg_replace_callback(
            '~<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>~is',
            fn (array $m): string => "\n[[LINK:".str_replace(['|', ']]'], '', html_entity_decode($m[2])).'|'.str_replace(['|', ']]'], ' ', RadarText::clean($m[3], 300))."]]\n",
            $html,
        );
        $html = (string) preg_replace('~<(br|/p|/div|/tr|/li|/h\d|/td)\b[^>]*>~i', "\n", $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Plain-text emails: the line before a project URL (or the line itself minus the URL) is its title.
     */
    private function markTextLinks(string $text): string
    {
        $lines = explode("\n", str_replace("\r", '', $text));
        $out = [];

        foreach ($lines as $index => $line) {
            if (preg_match('~https?://\S+~u', $line, $m)) {
                $title = trim(str_replace($m[0], '', $line)) ?: trim($lines[$index - 1] ?? '');

                if ($out !== [] && trim($lines[$index - 1] ?? '') === $title && $title !== '') {
                    array_pop($out);
                }

                $out[] = '[[LINK:'.str_replace(['|', ']]'], '', $m[0]).'|'.str_replace(['|', ']]'], ' ', $title).']]';

                continue;
            }

            $out[] = $line;
        }

        return implode("\n", $out);
    }
}
