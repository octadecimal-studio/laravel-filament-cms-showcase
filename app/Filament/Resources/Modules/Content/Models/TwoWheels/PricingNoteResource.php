<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\PricingNoteResource\Pages;
use App\Modules\Content\Models\TwoWheels\PricingNote;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania uwagami cennika.
 */
final class PricingNoteResource extends Resource
{
    protected static ?string $model = PricingNote::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Uwagi cennika';

    protected static ?string $modelLabel = 'Uwaga cennika';

    protected static ?string $pluralModelLabel = 'Uwagi cennika';

    protected static ?int $navigationSort = 56;

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
                Forms\Components\Section::make('Uwaga cennika')
                    ->schema([
                        Forms\Components\Textarea::make('content')
                            ->label('Treść')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Kolejność')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktywna')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('content')
                    ->label('Treść')
                    ->limit(80)
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktywna')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktywna')
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPricingNotes::route('/'),
            'create' => Pages\CreatePricingNote::route('/create'),
            'edit' => Pages\EditPricingNote::route('/{record}/edit'),
        ];
    }
}
