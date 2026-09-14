<?php

namespace App\Providers\Filament;

use App\Support\SiteHeader;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\View;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->favicon(fn (): ?string => SiteHeader::favicon())
            ->colors([
                'primary' => Color::hex('#7c3aed'),
            ])
            // Vaste groepsvolgorde. Zonder dit sorteert Filament op de volgorde
            // waarin hij de pagina's ontdekt, en dan wandelt "Instellingen" naar
            // boven zodra er een pagina bijkomt. Instellingen hoort onderaan:
            // dat open je zelden, in tegenstelling tot content en cijfers.
            ->navigationGroups(['Website', 'Groei', 'Instellingen'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => View::make('filament.admin.customizations')->render(),
            )
            // Altijd zichtbaar oogje in de topbalk, tussen de zoekbalk en het
            // accountmenu: opent de publieke site vanaf élke adminpagina.
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => View::make('filament.admin.view-site-button')->render(),
            )
            // Uitloggen onderaan de zijbalk, waar je het zoekt. Het zat al in het
            // accountmenu rechtsboven, maar dat moet je eerst openklappen.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => View::make('filament.admin.sidebar-logout')->render(),
            );
    }
}
