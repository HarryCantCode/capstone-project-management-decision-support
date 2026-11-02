<?php

namespace App\Support;

class PhilippineRegions
{
    /**
     * Regional groupings for the Philippines.
     */
    public const REGIONS = [
        'Metro-Manila' => 'Metro-Manila',
        'Mindanao'     => 'Mindanao',
        'North Luzon'  => 'North Luzon',
        'South Luzon'  => 'South Luzon',
        'Visayas'      => 'Visayas',
    ];

    /**
     * Get all regions.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::REGIONS;
    }

    /**
     * Get a region name from key or return the value.
     */
    public static function getName(string $codeOrName): string
    {
        return self::REGIONS[$codeOrName] ?? $codeOrName;
    }
}
