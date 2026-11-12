<?php

namespace App\Enums;

/**
 * How strongly CodeFanz can stand behind a technology.
 *
 * Admins choose Supported or Expertise. VerifiedInProjects is never stored: it is computed when a
 * published portfolio project links the technology (see Technology::proficiency()).
 */
enum TechnologyProficiency: string
{
    case Supported = 'supported';
    case Expertise = 'expertise';
    case VerifiedInProjects = 'verified_in_projects';

    /**
     * Levels an admin may assign by hand.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Supported, self::Expertise];
    }

    public function label(): string
    {
        return match ($this) {
            self::Supported => __('Supported'),
            self::Expertise => __('Expertise'),
            self::VerifiedInProjects => __('Verified in projects'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Supported => __('Supported — available on request'),
            self::Expertise => __('Expertise — strong in-house know-how'),
            self::VerifiedInProjects => __('Verified — used in published delivered projects'),
        };
    }
}
