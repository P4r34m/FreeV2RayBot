<?php

namespace App\Enums;

/**
 * Supported V2Ray panel software the bot can drive.
 * The value is what gets stored in `panels.type`.
 */
enum PanelType: string
{
    case ThreeXui = '3xui';
    case PasarGuard = 'pasarguard';
    case Remnawave = 'remnawave';

    public function label(): string
    {
        return match ($this) {
            self::ThreeXui => '3x-ui (Sanaei)',
            self::PasarGuard => 'PasarGuard',
            self::Remnawave => 'Remnawave',
        };
    }

    /**
     * Fully-qualified driver class that implements PanelDriver for this type.
     *
     * @return class-string
     */
    public function driverClass(): string
    {
        return match ($this) {
            self::ThreeXui => \App\Panels\Drivers\ThreeXuiDriver::class,
            self::PasarGuard => \App\Panels\Drivers\PasarGuardDriver::class,
            self::Remnawave => \App\Panels\Drivers\RemnawaveDriver::class,
        };
    }

    /**
     * Whether this panel authenticates with a username/password login.
     * (Remnawave uses a static API token instead.)
     */
    public function usesLogin(): bool
    {
        return match ($this) {
            self::ThreeXui, self::PasarGuard => true,
            self::Remnawave => false,
        };
    }
}
