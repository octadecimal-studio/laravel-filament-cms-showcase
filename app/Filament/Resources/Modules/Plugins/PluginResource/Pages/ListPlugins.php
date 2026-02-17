<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Plugins\PluginResource\Pages;

use App\Filament\Resources\Modules\Plugins\PluginResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlugins extends ListRecords
{
    protected static string $resource = PluginResource::class;

    protected static string $view = 'filament.resources.modules.plugins.plugin-resource.pages.list-plugins';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('search_store')
                ->label('Szukaj w sklepie')
                ->icon('heroicon-o-magnifying-glass')
                ->form([
                    \Filament\Forms\Components\Section::make('Wyszukiwanie')
                        ->schema([
                            \Filament\Forms\Components\TextInput::make('query')
                                ->label('Szukaj po słowie kluczowym')
                                ->placeholder('np. shield, palette, media')
                                ->autofocus()
                                ->live()
                                ->debounce(500)
                                ->helperText('Opcjonalne - zawęź wyniki do wybranych kategorii'),
                        ])
                        ->collapsible()
                        ->collapsed(false),
                    
                    \Filament\Forms\Components\Section::make('Kategorie')
                        ->description('Wybierz kategorie pluginów. Możesz wybrać kilka jednocześnie.')
                        ->schema([
                            \Filament\Forms\Components\CheckboxList::make('categories')
                                ->label('Kategorie')
                                ->options([
                                    'security' => 'Bezpieczeństwo',
                                    'ui' => 'Interfejs użytkownika',
                                    'content' => 'Treść',
                                    'integration' => 'Integracje',
                                    'developer' => 'Dla deweloperów',
                                    'other' => 'Inne',
                                ])
                                ->columns(2)
                                ->descriptions([
                                    'security' => 'Role, permissions, authentication',
                                    'ui' => 'Themes, colors, layouts, widgets',
                                    'content' => 'Media, files, rich text editors',
                                    'integration' => 'API, third-party services',
                                    'developer' => 'Tools, debugging, testing',
                                    'other' => 'Pozostałe pluginy',
                                ])
                                ->live()
                                ->helperText('Kliknij kategorię aby zobaczyć wszystkie pluginy z tej kategorii'),
                        ])
                        ->collapsible()
                        ->collapsed(false),
                ])
                ->modalHeading('Wyszukiwanie pluginów')
                ->modalWidth('5xl')
                ->modalSubmitActionLabel('Szukaj')
                ->modalCancelActionLabel('Zamknij')
                ->action(function (array $data) {
                    $query = $data['query'] ?? '';
                    $categories = $data['categories'] ?? [];
                    
                    // Jeśli nie wybrano kategorii i nie wpisano słowa, pokaż błąd
                    if (empty($query) && empty($categories)) {
                        \Filament\Notifications\Notification::make()
                            ->title('Wybierz kategorię lub wprowadź frazę wyszukiwania')
                            ->warning()
                            ->send();
                        return;
                    }

                    $service = app(\App\Modules\Plugins\Services\PluginService::class);
                    $results = $service->searchPackagist($query, $categories, 50);
                    
                    // Zapisz wyniki w sesji aby wyświetlić w widoku
                    session(['plugin_search_results' => $results]);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Znaleziono ' . count($results) . ' pluginów')
                        ->success()
                        ->send();
                    
                    // Przekieruj z powrotem do listy aby pokazać wyniki
                    return $this->redirect(static::getUrl());
                })
                ->color('info'),
            Actions\Action::make('sync_installed')
                ->label('Synchronizuj zainstalowane')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $service = app(\App\Modules\Plugins\Services\PluginService::class);
                    $service->syncInstalledPlugins();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Synchronizacja zakończona')
                        ->success()
                        ->send();
                }),
        ];
    }
}
