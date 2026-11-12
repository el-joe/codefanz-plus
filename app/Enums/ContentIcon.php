<?php

namespace App\Enums;

/**
 * Curated line icons (24×24, stroke) that admins can pick for services and industries.
 * Rendered by <x-ui.icon>; the markup is trusted and never comes from user input.
 */
enum ContentIcon: string
{
    case Globe = 'globe';
    case Smartphone = 'smartphone';
    case Code = 'code';
    case Layers = 'layers';
    case Building = 'building';
    case Cart = 'cart';
    case Sparkles = 'sparkles';
    case TrendingUp = 'trending-up';
    case Palette = 'palette';
    case Plug = 'plug';
    case Server = 'server';
    case Monitor = 'monitor';
    case Bot = 'bot';
    case Cloud = 'cloud';
    case Shield = 'shield';
    case Refresh = 'refresh';
    case Database = 'database';
    case HeartPulse = 'heart-pulse';
    case CreditCard = 'credit-card';
    case GraduationCap = 'graduation-cap';
    case Send = 'send';
    case Home = 'home';
    case Briefcase = 'briefcase';
    case Users = 'users';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $icon): array => [$icon->value => $icon->label()])->all();
    }

    public function label(): string
    {
        return str($this->value)->replace('-', ' ')->title()->toString();
    }

    /**
     * Inner SVG markup.
     */
    public function svg(): string
    {
        return match ($this) {
            self::Globe => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
            self::Smartphone => '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M11 18h2"/>',
            self::Code => '<path d="m9 8-4 4 4 4M15 8l4 4-4 4"/>',
            self::Layers => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
            self::Building => '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M10 21v-4h4v4"/>',
            self::Cart => '<circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2.5 3h2.6l2.4 12h11l2-8H6.3"/>',
            self::Sparkles => '<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8L12 3Z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/>',
            self::TrendingUp => '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
            self::Palette => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
            self::Plug => '<path d="M9 2v5M15 2v5M6 7h12v4a6 6 0 0 1-12 0V7ZM12 17v5"/>',
            self::Server => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01M7 16.5h.01"/>',
            self::Monitor => '<rect x="2.5" y="4" width="19" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
            self::Bot => '<rect x="4" y="8" width="16" height="12" rx="2.5"/><path d="M12 4v4M9 13h.01M15 13h.01M9 17h6"/>',
            self::Cloud => '<path d="M7 18a4.5 4.5 0 0 1-.6-8.96A6 6 0 0 1 18 8.5a4.75 4.75 0 0 1-.5 9.5H7Z"/>',
            self::Shield => '<path d="M12 3 4.5 6v6c0 4.5 3.2 7.8 7.5 9 4.3-1.2 7.5-4.5 7.5-9V6L12 3Z"/><path d="m9 12 2 2 4-4"/>',
            self::Refresh => '<path d="M20 11a8 8 0 0 0-14.6-4.5L4 8M4 4v4h4M4 13a8 8 0 0 0 14.6 4.5L20 16M20 20v-4h-4"/>',
            self::Database => '<ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5v13c0 1.7 3.6 3 8 3s8-1.3 8-3v-13M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
            self::HeartPulse => '<path d="M19.5 13.5 12 21l-7.5-7.5A4.9 4.9 0 0 1 12 6.6a4.9 4.9 0 0 1 7.5 6.9Z"/><path d="M3.5 12h4l1.5-2.5 2.5 5 1.5-2.5h4"/>',
            self::CreditCard => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/>',
            self::GraduationCap => '<path d="m2 9 10-5 10 5-10 5L2 9Z"/><path d="M6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5M22 9v6"/>',
            self::Send => '<path d="M21 3 3 10.5l7 2.5 2.5 7L21 3Z"/><path d="m10 13 4-4"/>',
            self::Home => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"/>',
            self::Briefcase => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18"/>',
            self::Users => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        };
    }
}
