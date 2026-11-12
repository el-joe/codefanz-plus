<?php

namespace App\Enums;

/**
 * Where a menu is rendered in the public layout.
 */
enum MenuLocation: string
{
    case Header = 'header';
    case FooterServices = 'footer_services';
    case FooterCompany = 'footer_company';
    case FooterResources = 'footer_resources';
    case FooterLegal = 'footer_legal';

    public function label(): string
    {
        return match ($this) {
            self::Header => __('Header navigation'),
            self::FooterServices => __('Footer · Services'),
            self::FooterCompany => __('Footer · Company'),
            self::FooterResources => __('Footer · Resources'),
            self::FooterLegal => __('Footer · Legal'),
        };
    }

    /**
     * Column heading shown above footer menus.
     */
    public function heading(): string
    {
        return match ($this) {
            self::Header => __('Menu'),
            self::FooterServices => __('Services'),
            self::FooterCompany => __('Company'),
            self::FooterResources => __('Resources'),
            self::FooterLegal => __('Legal'),
        };
    }

    /**
     * Only the header shows nested items (as dropdowns); footers stay flat.
     */
    public function allowsChildren(): bool
    {
        return $this === self::Header;
    }
}
