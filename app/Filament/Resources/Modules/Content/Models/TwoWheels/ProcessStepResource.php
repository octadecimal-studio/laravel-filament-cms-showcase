<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Scopes\TenantScope;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Action;
use App\Modules\Core\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\ProcessStepResource\Pages\ListProcessSteps;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\ProcessStepResource\Pages\CreateProcessStep;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\ProcessStepResource\Pages\EditProcessStep;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-list-bullet';

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
                TenantScope::class,
            ]);

        $user = auth()->user();
        if ($user && !($user->is_super_admin || $user->hasRole('super_admin'))) {
            $query->where('tenant_id', $user->tenant_id);
        }
        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Podstawowe informacje')
                    ->schema([
                        TextInput::make('step_number')
                            ->label('Numer kroku')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Numer kroku (1-10, unikalny)'),

                        TextInput::make('title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Opis')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('icon_name')
                            ->label('Nazwa ikony')
                            ->maxLength(255)
                            ->helperText('Nazwa ikony Heroicons (np. check-circle, clock)'),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Toggle::make('published')
                            ->label('Opublikowany')
                            ->default(false),

                        DateTimePicker::make('published_at')
                            ->label('Data publikacji')
                            ->visible(fn (Get $get): bool => $get('published') === true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('view_api')
                    ->label('Zobacz API')
                    ->icon('heroicon-o-code-bracket')
                    ->url(function () {
                        $u = auth()->user();
                        $base = url('/api/motorent/process-steps');
                        if ($u?->isSuperAdmin()) {
                            return $base;
                        }
                        return $base . '?tenant_id=' . ($u?->tenant_id ?? Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id') ?? '');
                    })
                    ->openUrlInNewTab()
                    ->color('info'),
            ])
            ->columns([
                TextColumn::make('step_number')
                    ->label('Krok')
                    ->sortable()
                    ->badge(),

                TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Opis')
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('icon_name')
                    ->label('Ikona')
                    ->badge()
                    ->toggleable(),

                IconColumn::make('published')
                    ->label('Opublikowany')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('step_number')
            ->filters([
                TernaryFilter::make('published')
                    ->label('Opublikowany')
                    ->placeholder('Wszystkie')
                    ->trueLabel('Tak')
                    ->falseLabel('Nie'),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListProcessSteps::route('/'),
            'create' => CreateProcessStep::route('/create'),
            'edit' => EditProcessStep::route('/{record}/edit'),
        ];
    }
}
