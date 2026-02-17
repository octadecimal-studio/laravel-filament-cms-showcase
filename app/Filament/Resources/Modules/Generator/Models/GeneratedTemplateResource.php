<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Generator\Models;

use App\Filament\Resources\Modules\Generator\Models\GeneratedTemplateResource\Pages;
use App\Modules\Generator\Models\GeneratedTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament Resource dla wygenerowanych szablonów AI.
 */
final class GeneratedTemplateResource extends Resource
{
    protected static ?string $model = GeneratedTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Wygenerowane szablony';

    protected static ?string $modelLabel = 'Wygenerowany szablon';

    protected static ?string $pluralModelLabel = 'Wygenerowane szablony';

    protected static ?string $navigationGroup = 'Generator';

    protected static ?int $navigationSort = 2;

    /**
     * Tylko super admin widzi wygenerowane szablony.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacje')
                    ->schema([
                        Forms\Components\TextInput::make('prompt')
                            ->label('Prompt')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('model')
                            ->label('Model AI')
                            ->disabled()
                            ->options([
                                'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                                'claude-opus-3-20240229' => 'Claude Opus 3',
                                'gpt-4-turbo-preview' => 'GPT-4 Turbo',
                                'gpt-4' => 'GPT-4',
                            ]),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Oczekuje',
                                'generating' => 'W trakcie',
                                'completed' => 'Zakończone',
                                'failed' => 'Nieudane',
                            ])
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prompt')
                    ->label('Prompt')
                    ->limit(50)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('model')
                    ->label('Model')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'generating' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('metadata.tokens_total')
                    ->label('Tokeny')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return json_encode($state, JSON_UNESCAPED_UNICODE);
                        }
                        return $state ?? 'N/A';
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Oczekuje',
                        'generating' => 'W trakcie',
                        'completed' => 'Zakończone',
                        'failed' => 'Nieudane',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('model')
                    ->label('Model')
                    ->options([
                        'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
                        'claude-opus-3-20240229' => 'Claude Opus 3',
                        'gpt-4-turbo-preview' => 'GPT-4 Turbo',
                        'gpt-4' => 'GPT-4',
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informacje')
                    ->schema([
                        Infolists\Components\TextEntry::make('prompt')
                            ->label('Prompt')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('model')
                            ->label('Model AI'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success',
                                'generating' => 'warning',
                                'failed' => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Utworzono')
                            ->dateTime('d.m.Y H:i:s'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Wygenerowany kod')
                    ->schema([
                        Infolists\Components\TextEntry::make('generated_code')
                            ->label('Kod')
                            ->formatStateUsing(function ($state) {
                                if (is_array($state)) {
                                    return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                }
                                return $state ?? 'Brak danych';
                            })
                            ->columnSpanFull()
                            ->copyable()
                            ->copyMessage('Kod skopiowany!'),
                    ])
                    ->visible(fn (GeneratedTemplate $record): bool => $record->status === 'completed' && ! empty($record->generated_code)),

                Infolists\Components\Section::make('Metadane')
                    ->schema([
                        Infolists\Components\TextEntry::make('metadata')
                            ->label('Metadane')
                            ->formatStateUsing(function ($state) {
                                if (is_array($state)) {
                                    return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                }
                                return $state ?? 'Brak danych';
                            })
                            ->columnSpanFull()
                            ->copyable()
                            ->copyMessage('Metadane skopiowane!'),
                    ])
                    ->visible(fn (GeneratedTemplate $record): bool => ! empty($record->metadata)),

                Infolists\Components\Section::make('Błąd')
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Komunikat błędu')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (GeneratedTemplate $record): bool => $record->status === 'failed' && ! empty($record->error_message)),
            ]);
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
            'index' => Pages\ListGeneratedTemplates::route('/'),
            'view' => Pages\ViewGeneratedTemplate::route('/{record}'),
        ];
    }
}
