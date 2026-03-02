<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Datlechin\FilamentMenuBuilder\Resources\MenuResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Panel';

    protected static string $view = 'filament.pages.dashboard';

    protected function getHeaderActions(): array
    {
        $actions = [];
        
        // Przycisk Menu Builder - tylko jeśli plugin jest zarejestrowany
        if (class_exists(\Datlechin\FilamentMenuBuilder\Resources\MenuResource::class)) {
            try {
                $menuResource = \Datlechin\FilamentMenuBuilder\Resources\MenuResource::class;
                if (method_exists($menuResource, 'getUrl')) {
                    $actions[] = Action::make('menu_builder')
                        ->label('Menu Builder')
                        ->icon('heroicon-o-bars-3')
                        ->color('primary')
                        ->url($menuResource::getUrl('index'));
                }
            } catch (\Throwable $e) {
                // Jeśli resource nie jest jeszcze zarejestrowany, pomiń przycisk
            }
        }
        
        // Przycisk Clear Cache
        $actions[] = Action::make('clear_cache')
            ->label('Clear Cache')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Wyczyść cache')
            ->modalDescription('Czy na pewno chcesz wyczyścić wszystkie cache? Ta operacja może chwilę potrwać.')
            ->modalSubmitActionLabel('Wyczyść')
            ->modalCancelActionLabel('Anuluj')
            ->action(function () {
                try {
                    Artisan::call('optimize:clear');
                    
                    Notification::make()
                        ->title('Cache wyczyszczony')
                        ->body('Wszystkie cache zostały pomyślnie wyczyszczone.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Błąd podczas czyszczenia cache')
                        ->body('Wystąpił błąd: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
        
        return $actions;
    }
}
