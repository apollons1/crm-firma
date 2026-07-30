<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->profile(EditProfile::class)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')

            // ── Autentificare cu doi factori (2FA) ──────────────────────────
            // Obligatorie pentru super_admin/admin, opțională (activabilă din
            // profil) pentru sales_manager/sales_rep. La prima autentificare
            // după activare, super_admin/admin sunt forțați să o configureze
            // înainte de a putea folosi CRM-ul (comportament implicit Filament
            // când multiFactorAuthentication este obligatoriu).
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false,
            )

            // ── Branding ──────────────────────────────────────────────────
            ->brandName('CRM AktivTherm')
            ->brandLogo(fn () => file_exists(public_path('logo.png'))
                ? asset('logo.png')
                : null
            )
            ->brandLogoHeight('2.5rem')
            ->favicon(fn () => file_exists(public_path('favicon.ico'))
                ? asset('favicon.ico')
                : null
            )

            // ── Culori brand AktivTherm ────────────────────────────────────
            ->colors([
                'primary' => Color::hex('#E63946'),
                'secondary' => Color::hex('#48CAE4'),
            ])

            // ── Footer custom ──────────────────────────────────────────────
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): HtmlString => new HtmlString(
                    '<div class="py-3 text-center text-xs text-gray-400">'
                    .'© '.date('Y').' AktivTherm &mdash; Făcut cu '
                    .'<span class="font-medium text-gray-500">Claude Code</span>'
                    .' &mdash; Quick Deploy test ✅'
                    .'</div>'
                ),
            )

            // ── Resurse & pagini ───────────────────────────────────────────
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->plugin(FilamentShieldPlugin::make())

            // ── Middleware ─────────────────────────────────────────────────
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
