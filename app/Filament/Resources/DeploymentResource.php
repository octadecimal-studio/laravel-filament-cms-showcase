<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DeploymentResource\Pages;
use App\Modules\Deploy\Models\Deployment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla deploymentów.
 *
 * Wyświetla historię wdrożeń i logi.
 */
class DeploymentResource extends Resource
{
    protected static ?string $model = Deployment::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Deploymenty';

    protected static ?string $modelLabel = 'Deployment';

    protected static ?string $pluralModelLabel = 'Deploymenty';

    protected static ?string $navigationGroup = 'DevOps';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacje')
                    ->schema([
                        Forms\Components\TextInput::make('version')
                            ->label('Wersja')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Oczekujący',
                                'in_progress' => 'W trakcie',
                                'completed' => 'Zakończony',
                                'failed' => 'Błąd',
                                'rolled_back' => 'Wycofany',
                            ])
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('started_at')
                            ->label('Rozpoczęto')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Zakończono')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Metadane')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Konfiguracja')
                            ->disabled(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Status deploymentu')
                    ->schema([
                        Infolists\Components\TextEntry::make('version')
                            ->label('Wersja'),

                        Infolists\Components\BadgeEntry::make('status')
                            ->label('Status')
                            ->colors([
                                'gray' => 'pending',
                                'warning' => 'in_progress',
                                'success' => 'completed',
                                'danger' => 'failed',
                                'info' => 'rolled_back',
                            ]),

                        Infolists\Components\TextEntry::make('started_at')
                            ->label('Rozpoczęto')
                            ->dateTime('d.m.Y H:i:s'),

                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Zakończono')
                            ->dateTime('d.m.Y H:i:s'),

                        Infolists\Components\TextEntry::make('duration')
                            ->label('Czas trwania')
                            ->getStateUsing(function (Deployment $record) {
                                if (!$record->started_at || !$record->completed_at) {
                                    return '-';
                                }
                                return $record->started_at->diffForHumans($record->completed_at, true);
                            }),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Konfiguracja')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('metadata')
                            ->label('Metadane'),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Logi')
                    ->schema([
                        Infolists\Components\ViewEntry::make('logs')
                            ->label('')
                            ->view('filament.infolists.entries.deployment-logs'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version')
                    ->label('Wersja')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'info' => 'rolled_back',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Oczekujący',
                        'in_progress' => 'W trakcie',
                        'completed' => 'Zakończony',
                        'failed' => 'Błąd',
                        'rolled_back' => 'Wycofany',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('metadata.environment')
                    ->label('Środowisko')
                    ->badge()
                    ->color(fn ($state) => $state === 'production' ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('metadata.deploy_type')
                    ->label('Typ')
                    ->badge(),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Rozpoczęto')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Zakończono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('logs_count')
                    ->label('Logi')
                    ->getStateUsing(fn ($record) => count($record->logs ?? [])),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Oczekujący',
                        'in_progress' => 'W trakcie',
                        'completed' => 'Zakończony',
                        'failed' => 'Błąd',
                        'rolled_back' => 'Wycofany',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('retry')
                    ->label('Ponów')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->action(function (Deployment $record) {
                        // Tu można dodać logikę ponowienia
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s'); // Odświeżaj co 10 sekund dla aktywnych deploymentów
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
            'index' => Pages\ListDeployments::route('/'),
            'view' => Pages\ViewDeployment::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'in_progress')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
