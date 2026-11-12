<?php

namespace App\Enums;

/**
 * Honest connection state of a social account. Only "connected" accounts are published to through the API; every
 * other state turns its variants into "ready for manual export" at the scheduled time.
 */
enum SocialAccountStatus: string
{
    case Connected = 'connected';
    case RequiresAppApproval = 'requires_app_approval';
    case RequiresCredentials = 'requires_credentials';
    case TokenExpired = 'token_expired';
    case ManualMode = 'manual_mode';
    case Disconnected = 'disconnected';

    public function label(): string
    {
        return match ($this) {
            self::Connected => __('Connected'),
            self::RequiresAppApproval => __('Needs app approval'),
            self::RequiresCredentials => __('Requires credentials'),
            self::TokenExpired => __('Reconnect required'),
            self::ManualMode => __('Manual export'),
            self::Disconnected => __('Not connected'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::RequiresAppApproval, self::TokenExpired => 'warning',
            self::RequiresCredentials, self::ManualMode, self::Disconnected => 'neutral',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Connected => __('Approved posts are published through the official API.'),
            self::RequiresAppApproval => __('The platform has not approved the app for posting yet. Posts are prepared for manual export.'),
            self::RequiresCredentials => __('Save the app credentials in Settings → Social apps, then connect.'),
            self::TokenExpired => __('The access token expired or was revoked. Reconnect to publish directly again.'),
            self::ManualMode => __('Posts are prepared as a copy-ready package and posted by hand.'),
            self::Disconnected => __('Tokens were removed. Posts are prepared for manual export.'),
        };
    }

    public function canPublish(): bool
    {
        return $this === self::Connected;
    }
}
