<?php

namespace App\Enums;

/**
 * Abilities an MCP client token can carry. Each scope also needs a permission on the client's owner, checked on
 * every call, so a token can never do more than the person who owns it. Nothing here grants finance, user,
 * role, credential or security access.
 */
enum McpScope: string
{
    case ContentRead = 'content:read';
    case ContentDraft = 'content:draft';
    case LeadsRead = 'leads:read';
    case LeadsWrite = 'leads:write';
    case SeoRead = 'seo:read';
    case SeoPropose = 'seo:propose';
    case AnalyticsRead = 'analytics:read';
    case ApprovalsRead = 'approvals:read';
    case SocialDraft = 'social:draft';
    case ApprovalsApply = 'approvals:apply';

    public function label(): string
    {
        return match ($this) {
            self::ContentRead => __('Read website content'),
            self::ContentDraft => __('Draft content and propose changes'),
            self::LeadsRead => __('Read leads'),
            self::LeadsWrite => __('Create leads'),
            self::SeoRead => __('Read SEO audits'),
            self::SeoPropose => __('Propose SEO metadata'),
            self::AnalyticsRead => __('Read analytics summaries'),
            self::ApprovalsRead => __('Read approval status'),
            self::SocialDraft => __('Create social post drafts'),
            self::ApprovalsApply => __('Apply human-approved changes'),
        };
    }

    /**
     * Whether calls with this scope create or change data (blocked by the MCP writes kill switch).
     */
    public function isWrite(): bool
    {
        return in_array($this, [self::ContentDraft, self::LeadsWrite, self::SeoPropose, self::SocialDraft, self::ApprovalsApply], true);
    }

    /**
     * Permission the client's owner must hold for the token to use this scope. Applying a change additionally
     * needs the change request's own required permission, checked per request.
     */
    public function ownerPermission(): Permission
    {
        return match ($this) {
            self::ContentRead => Permission::AdminAccess,
            self::ContentDraft => Permission::ContentManage,
            self::LeadsRead => Permission::LeadsView,
            self::LeadsWrite => Permission::LeadsManage,
            self::SeoRead, self::SeoPropose, self::AnalyticsRead => Permission::SeoManage,
            self::ApprovalsRead, self::ApprovalsApply => Permission::AdminAccess,
            self::SocialDraft => Permission::SocialManage,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $scope): string => $scope->value, self::cases());
    }
}
