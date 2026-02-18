<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\ProcessStepResource\Pages;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\ProcessStepResource\RelationManagers;
use App\Modules\Content\Models\TwoWheels\ProcessStep;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania krokami procesu wypożyczenia.
 */
final class ProcessStepResource extends Resource
{
    protected static ?string $model = ProcessStep::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Kroki procesu';

    protected static ?string $modelLabel = 'Krok procesu';

    protected static ?string $pluralModelLabel = 'Kroki procesu';

    protected static ?int $navigationSort = 60;

    /**
     * Filtruj dane po tenant_id.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
                \App\Modules\Core\Scopes\TenantScope::class,
            ]);

        $user = auth()->user();
        if ($user && !($user->is_super_admin || $user->hasRole('super_admin'))) {
            $query->where('tenant_id', $user->tenant_id);
        }
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Podstawowe informacje')
                    ->schema([
                        Forms\Components\TextInput::make('step_number')
                            ->label('Numer kroku')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Numer kroku (1-10, unikalny)'),

                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('icon_name')
                            ->label('Nazwa ikony')
                            ->maxLength(255)
                            ->helperText('Nazwa ikony Heroicons (np. check-circle, clock)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('published')
                            ->label('Opublikowany')
                            ->default(false),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Data publikacji')
                            ->visible(fn (Forms\Get $get): bool => $get('published') === true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Tables\Actions\Action::make('view_api')
                    ->label('Zobacz API')
                    ->icon('heroicon-o-code-bracket')
                    ->url(function () {
                        $u = auth()->user();
                        $base = url('/api/motorent/process-steps');
                        if ($u?->isSuperAdmin()) {
                            return $base;
                        }
                        return $base . '?tenant_id=' . ($u?->tenant_id ?? \App\Modules\Core\Models\Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id') ?? '');
                    })
                    ->openUrlInNewTab()
                    ->color('info'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('step_number')
                    ->label('Krok')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('icon_name')
                    ->label('Ikona')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('published')
                    ->label('Opublikowany')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('step_number')
            ->filters([
                Tables\Filters\TernaryFilter::make('published')
                    ->label('Opublikowany')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
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
            'index' => Pages\ListProcessSteps::route('/'),
            'create' => Pages\CreateProcessStep::route('/create'),
            'edit' => Pages\EditProcessStep::route('/{record}/edit'),
        ];
    }
}
