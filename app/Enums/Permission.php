<?php

namespace App\Enums;

enum Permission: string
{
    case AdminAccess = 'admin.access';
    case LeadsView = 'leads.view';
    case LeadsManage = 'leads.manage';
    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';
    case ProjectsView = 'projects.view';
    case ProjectsManage = 'projects.manage';
    case PaymentsCreateLinks = 'payments.create-links';
    case PaymentsRefundApprove = 'payments.refund-approve';
    case ContentManage = 'content.manage';
    case ContentApprove = 'content.approve';
    case SeoManage = 'seo.manage';
    case SeoApprove = 'seo.approve';
    case SocialManage = 'social.manage';
    case SocialApprove = 'social.approve';
    case MediaManage = 'media.manage';
    case AiUse = 'ai.use';
    case McpManage = 'mcp.manage';
    case RadarView = 'radar.view';
    case AutomationManage = 'automation.manage';
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case SupportManage = 'support.manage';

    /**
     * Permissions that allow a user to review at least one type of change request.
     *
     * @return list<self>
     */
    public static function approvalPermissions(): array
    {
        return [self::ContentApprove, self::SeoApprove, self::SocialApprove];
    }
}
