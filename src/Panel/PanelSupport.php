<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Panel;

/**
 * Detects which admin panels are installed, without depending on any of them. SIS ships no UI (it is
 * headless); these class_exists probes let an opt-in integration — or `sis:doctor`, or the docs — report
 * where the RegisterPanelPresenter can be wired. Absence is the norm, never an error.
 */
final class PanelSupport
{
    /** Filament's panel class — present only when filament/filament is installed. */
    public const string FILAMENT = 'Filament\\Panel';

    /** Nova's entry class — present only when laravel/nova is installed. */
    public const string NOVA = 'Laravel\\Nova\\Nova';

    public static function filamentAvailable(): bool
    {
        return class_exists(self::FILAMENT);
    }

    public static function novaAvailable(): bool
    {
        return class_exists(self::NOVA);
    }

    /** @return list<string> the detected panel slugs, e.g. ['filament'] — empty when the app is headless */
    public static function detected(): array
    {
        $panels = [];

        if (self::filamentAvailable()) {
            $panels[] = 'filament';
        }

        if (self::novaAvailable()) {
            $panels[] = 'nova';
        }

        return $panels;
    }
}
