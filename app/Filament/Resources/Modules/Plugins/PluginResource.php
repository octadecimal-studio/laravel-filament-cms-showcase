<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Plugins;

use App\Filament\Resources\Modules\Plugins\PluginResource\Pages;
use App\Models\Plugin;
use App\Modules\Plugins\Services\PluginService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class PluginResource extends Resource
{
    protected static ?string $model = Plugin::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Pluginy';

    protected static ?string $modelLabel = 'Plugin';

    protected static ?string $pluralModelLabel = 'Pluginy';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    /**
     * Tylko super admin widzi pluginy.
     */
    public static function shouldRegisterNavigation(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user->is_super_admin || (method_exists($user, 'hasRole') && $user->hasRole('super_admin'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nazwa')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('package')
                            ->label('Package Composer')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Np. bezhansalleh/filament-shield')
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('class_name')
                            ->label('Klasa pluginu (FQCN)')
                            ->maxLength(255)
                            ->helperText('Pełna nazwa klasy, np. BezhanSalleh\FilamentShield\FilamentShieldPlugin')
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('detect')
                                    ->icon('heroicon-o-sparkles')
                                    ->action(function (\Filament\Forms\Set $set, \Filament\Forms\Get $get) {
                                        $package = $get('package');
                                        if (empty($package)) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Wprowadź package Composer')
                                                ->warning()
                                                ->send();
                                            return;
                                        }
                                        
                                        $service = app(\App\Modules\Plugins\Services\PluginService::class);
                                        $detectedClass = $service->detectPluginClass($package);
                                        
                                        if ($detectedClass) {
                                            $set('class_name', $detectedClass);
                                            
                                            // Sprawdź kompatybilność z Filament v3
                                            $compatibility = $service->checkCompatibility($package, $detectedClass);
                                            if (!$compatibility['compatible']) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Klasa wykryta - UWAGA: Problem kompatybilności')
                                                    ->body($compatibility['message'])
                                                    ->warning()
                                                    ->persistent()
                                                    ->send();
                                            } else {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Klasa wykryta')
                                                    ->body("Znaleziono: {$detectedClass}")
                                                    ->success()
                                                    ->send();
                                            }
                                        } else {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Nie znaleziono klasy')
                                                ->body('Sprawdź dokumentację pakietu lub wypełnij ręcznie.')
                                                ->warning()
                                                ->send();
                                        }
                                    })
                            ),

                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('version')
                            ->label('Wersja')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('author')
                            ->label('Autor')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('homepage')
                            ->label('Strona główna')
                            ->url()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('repository')
                            ->label('Repository')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_installed')
                            ->label('Zainstalowany')
                            ->disabled()
                            ->helperText('Czy pakiet jest zainstalowany przez Composer'),

                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Włączony')
                            ->helperText('Czy plugin jest aktywny w panelu'),

                        Forms\Components\Toggle::make('is_official')
                            ->label('Oficjalny plugin Filament')
                            ->helperText('Czy to oficjalny plugin z filamentphp.com'),

                        Forms\Components\Select::make('category')
                            ->label('Kategoria')
                            ->options([
                                'security' => 'Bezpieczeństwo',
                                'ui' => 'Interfejs użytkownika',
                                'content' => 'Treść',
                                'integration' => 'Integracje',
                                'developer' => 'Dla deweloperów',
                                'other' => 'Inne',
                            ])
                            ->searchable(),

                        Forms\Components\TagsInput::make('tags')
                            ->label('Tagi')
                            ->helperText('Tagi do wyszukiwania'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Konfiguracja')
                    ->schema([
                        Forms\Components\KeyValue::make('config')
                            ->label('Konfiguracja pluginu')
                            ->keyLabel('Klucz')
                            ->valueLabel('Wartość')
                            ->helperText('Opcjonalna konfiguracja pluginu'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nazwa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('package')
                    ->label('Package')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('version')
                    ->label('Wersja')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_installed')
                    ->label('Zainstalowany')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Włączony')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategoria')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'security' => 'danger',
                        'ui' => 'primary',
                        'content' => 'success',
                        'integration' => 'info',
                        'developer' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('downloads_count')
                    ->label('Pobrania')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_installed')
                    ->label('Zainstalowany')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Włączony')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategoria')
                    ->options([
                        'security' => 'Bezpieczeństwo',
                        'ui' => 'Interfejs użytkownika',
                        'content' => 'Treść',
                        'integration' => 'Integracje',
                        'developer' => 'Dla deweloperów',
                        'other' => 'Inne',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('enable')
                    ->label('Włącz')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Plugin $record) {
                        $record->update(['is_enabled' => true]);
                        Notification::make()
                            ->title('Plugin włączony')
                            ->body("Plugin {$record->name} został włączony. Odśwież stronę aby zobaczyć zmiany.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Plugin $record) => !$record->is_enabled && $record->is_installed),

                Tables\Actions\Action::make('disable')
                    ->label('Wyłącz')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Plugin $record) {
                        $record->update(['is_enabled' => false]);
                        Notification::make()
                            ->title('Plugin wyłączony')
                            ->body("Plugin {$record->name} został wyłączony.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Plugin $record) => $record->is_enabled),

                Tables\Actions\Action::make('install')
                    ->label('Zainstaluj')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Instalacja pluginu')
                    ->modalDescription(fn (Plugin $record) => "Czy na pewno chcesz zainstalować plugin {$record->name}? To może zająć kilka minut.")
                    ->action(function (Plugin $record) {
                        $service = app(PluginService::class);
                        try {
                            $service->install($record->package);
                            $record->update(['is_installed' => true, 'installed_at' => now()]);
                            Notification::make()
                                ->title('Plugin zainstalowany')
                                ->body("Plugin {$record->name} został zainstalowany pomyślnie.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Błąd instalacji')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Plugin $record) => !$record->is_installed),

                Tables\Actions\Action::make('uninstall')
                    ->label('Odinstaluj')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Odinstalowanie pluginu')
                    ->modalDescription(fn (Plugin $record) => "Czy na pewno chcesz odinstalować plugin {$record->name}?")
                    ->action(function (Plugin $record) {
                        $service = app(PluginService::class);
                        try {
                            $service->uninstall($record->package);
                            $record->update(['is_installed' => false, 'is_enabled' => false]);
                            Notification::make()
                                ->title('Plugin odinstalowany')
                                ->body("Plugin {$record->name} został odinstalowany.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Błąd odinstalowania')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Plugin $record) => $record->is_installed),

                Tables\Actions\Action::make('detect_class')
                    ->label('Wykryj klasę')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Wykrywanie klasy pluginu')
                    ->modalDescription('System spróbuje automatycznie znaleźć klasę pluginu Filament dla tego pakietu.')
                    ->action(function (Plugin $record) {
                        $service = app(PluginService::class);
                        $detectedClass = $service->detectPluginClass($record->package);
                        
                        if ($detectedClass) {
                            $record->update(['class_name' => $detectedClass]);
                            Notification::make()
                                ->title('Klasa wykryta')
                                ->body("Znaleziono klasę: {$detectedClass}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Nie znaleziono klasy')
                                ->body('Nie udało się automatycznie wykryć klasy pluginu. Sprawdź dokumentację pakietu.')
                                ->warning()
                                ->send();
                        }
                    })
                    ->visible(fn (Plugin $record) => $record->is_installed && empty($record->class_name)),

                Tables\Actions\Action::make('search')
                    ->label('Szukaj w sklepie')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->url(fn (Plugin $record) => "https://packagist.org/packages/{$record->package}", shouldOpenInNewTab: true)
                    ->visible(fn (Plugin $record) => !empty($record->package)),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Modules\Plugins\PluginResource\Pages\ListPlugins::route('/'),
            'create' => \App\Filament\Resources\Modules\Plugins\PluginResource\Pages\CreatePlugin::route('/create'),
            'edit' => \App\Filament\Resources\Modules\Plugins\PluginResource\Pages\EditPlugin::route('/{record}/edit'),
        ];
    }
}
