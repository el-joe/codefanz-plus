<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum Role: string
{
    case SuperAdmin = 'super-admin';
    case Administrator = 'administrator';
    case ProjectManager = 'project-manager';
    case SalesManager = 'sales-manager';
    case ContentManager = 'content-manager';
    case SeoSpecialist = 'seo-specialist';
    case SocialMediaManager = 'social-media-manager';
    case Developer = 'developer';
    case FinanceManager = 'finance-manager';
    case SupportAgent = 'support-agent';
    case Client = 'client';

    /**
     * Roles that must use two-factor authentication before accessing the admin area.
     */
    public function requiresTwoFactor(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Administrator, self::FinanceManager], true);
    }

    public function isStaff(): bool
    {
        return $this !== self::Client;
    }

    public function label(): string
    {
        return __(Str::headline($this->value));
    }

    /**
     * Roles that can be assigned from the staff admin. Client accounts are invited from a customer's page.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role->isStaff()));
    }

    /**
     * Default permissions granted to the role. Super Admin bypasses checks through Gate::before.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::Administrator => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission): bool => ! in_array($permission, [
                    Permission::UsersManage,
                    Permission::McpManage,
                    Permission::PaymentsRefundApprove,
                ], true),
            )),
            self::ProjectManager => [Permission::AdminAccess, Permission::LeadsView, Permission::CustomersView, Permission::CustomersManage, Permission::ProjectsManage, Permission::PaymentsCreateLinks, Permission::SupportManage],
            self::SalesManager => [Permission::AdminAccess, Permission::LeadsView, Permission::LeadsManage, Permission::CustomersView, Permission::CustomersManage, Permission::ProjectsView, Permission::RadarView],
            self::ContentManager => [Permission::AdminAccess, Permission::ContentManage, Permission::ContentApprove, Permission::MediaManage, Permission::AiUse],
            self::SeoSpecialist => [Permission::AdminAccess, Permission::SeoManage, Permission::SeoApprove, Permission::AiUse],
            self::SocialMediaManager => [Permission::AdminAccess, Permission::SocialManage, Permission::SocialApprove, Permission::MediaManage, Permission::AiUse],
            self::Developer => [Permission::AdminAccess, Permission::ProjectsManage, Permission::SupportManage],
            self::FinanceManager => [Permission::AdminAccess, Permission::CustomersView, Permission::ProjectsView, Permission::PaymentsCreateLinks, Permission::PaymentsRefundApprove],
            self::SupportAgent => [Permission::AdminAccess, Permission::ProjectsView, Permission::SupportManage],
            self::Client => [],
        };
    }
}
