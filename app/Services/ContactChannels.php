<?php

namespace App\Services;

/**
 * Contact channels built from Settings → Contact. Channels without a configured value are left out.
 */
class ContactChannels
{
    public function __construct(private Settings $settings) {}

    /**
     * @return list<array{key: string, label: string, value: string, hint: string|null, href: string|null, external: bool, icon: string}>
     */
    public function all(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $email = $this->settings->get('contact', 'email');
        $whatsapp = $this->settings->get('contact', 'whatsapp');
        $phone = $this->settings->get('contact', 'phone');
        $meeting = $this->settings->get('contact', 'meeting_url');
        $hours = ($this->settings->get('contact', 'hours') ?? [])[$locale] ?? null;

        $channels = [];

        if (is_string($email) && $email !== '') {
            $channels[] = ['key' => 'email', 'label' => __('Email'), 'value' => $email, 'hint' => __('Best for detailed briefs'), 'href' => 'mailto:'.$email, 'external' => false, 'icon' => 'mail'];
        }

        if (is_string($whatsapp) && $whatsapp !== '') {
            $channels[] = ['key' => 'whatsapp', 'label' => __('WhatsApp'), 'value' => $whatsapp, 'hint' => __('Quick questions, fast replies'), 'href' => self::whatsappUrl($whatsapp), 'external' => true, 'icon' => 'whatsapp'];
        }

        if (is_string($phone) && $phone !== '') {
            $channels[] = ['key' => 'phone', 'label' => __('Phone'), 'value' => $phone, 'hint' => $hours, 'href' => self::telUrl($phone), 'external' => false, 'icon' => 'phone'];
        }

        if (is_string($meeting) && str_starts_with($meeting, 'https://') && filter_var($meeting, FILTER_VALIDATE_URL) !== false) {
            $channels[] = ['key' => 'meeting', 'label' => __('Book a meeting'), 'value' => __('Video call'), 'hint' => __('Pick a time that works for you'), 'href' => $meeting, 'external' => true, 'icon' => 'calendar'];
        }

        // The AI project assistant arrives in Milestone 5; shown honestly as not available yet.
        $channels[] = ['key' => 'assistant', 'label' => __('AI Project Assistant'), 'value' => __('Coming soon'), 'hint' => __('Describe your idea step by step'), 'href' => null, 'external' => false, 'icon' => 'bot'];

        return $channels;
    }

    public static function whatsappUrl(string $number): string
    {
        return 'https://wa.me/'.preg_replace('/\D/', '', $number);
    }

    public static function telUrl(string $number): string
    {
        return 'tel:'.preg_replace('/[^0-9+]/', '', $number);
    }
}
