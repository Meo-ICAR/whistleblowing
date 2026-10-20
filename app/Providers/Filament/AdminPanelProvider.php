<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Models\Company;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            // Sicurezza: i gestori accedono a dati di segnalanti anonimi molto
            // sensibili, quindi la 2FA tramite app authenticator (TOTP) è
            // obbligatoria per ogni account al primo accesso utile.
            // In locale con APP_DEBUG=true resta disponibile ma non
            // obbligatoria, per non intralciare lo sviluppo/i test manuali.
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: fn () => ! config('app.debug'))
            ->tenant(Company::class, slugAttribute: 'slug')
            ->tenantMenu(true)
            // White-label: il logo/nome nella barra di navigazione seguono
            // l'azienda (tenant) selezionata, non solo l'avatar nel menu
            // tenant. Nessun logo caricato (o nessun tenant, es. pagina di
            // login) → torna al logo di default della piattaforma.
            // Riusa Company::getFilamentAvatarUrl() per non duplicare la
            // logica di risoluzione del disco (deve essere sempre 'public').
            ->brandLogo(fn (): string => Filament::getTenant()?->getFilamentAvatarUrl() ?? asset('brand/logos/unico-whistle.jpg'))
            ->brandLogoHeight('5rem')
            ->favicon(asset('brand/icons/unico-whistle-64.png'))
            ->brandName(fn (): string => Filament::getTenant()?->name ?? config('app.name'))
            // Bugfix: la closure va sull'intero array restituito da colors(),
            // non su un singolo valore al suo interno, altrimenti Filament
            // tenta un array_map() su una Closure e va in errore fatale su
            // OGNI pagina del pannello.
            ->colors(fn () => [
                'primary' => Filament::getTenant()?->brand_color ?? '#1d4ed8',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                //   FilamentInfoWidget::class,
            ])
            ->navigationItems([
                NavigationItem::make('Manuale Operativo Tecnico')
                    ->icon('heroicon-o-document-text')
                    ->group('Documentazione')
                    ->sort(1)
                    ->url(fn (): string => route('docs.manuale-tecnico'), shouldOpenInNewTab: true),
                NavigationItem::make('Manuale Utente (PDF)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->group('Documentazione')
                    ->sort(2)
                    ->url(fn (): string => route('docs.manuale-utente'), shouldOpenInNewTab: true),
                NavigationItem::make('Manuale Webmaster')
                    ->icon('heroicon-o-globe-alt')
                    ->group('Documentazione')
                    ->sort(3)
                    ->url(fn (): string => route('docs.manuale-webmaster'), shouldOpenInNewTab: true),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
