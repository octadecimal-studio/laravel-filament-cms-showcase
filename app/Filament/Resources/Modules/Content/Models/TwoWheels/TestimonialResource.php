<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\TestimonialResource\Pages;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\TestimonialResource\RelationManagers;
use App\Modules\Content\Models\TwoWheels\Testimonial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania opiniami klientów.
 */
final class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Opinie';

    protected static ?string $modelLabel = 'Opinia';

    protected static ?string $pluralModelLabel = 'Opinie';

    protected static ?string $navigationGroup = 'MotoRent Demo';

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
                        Forms\Components\TextInput::make('order')
                            ->label('Kolejność')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('author_name')
                            ->label('Autor')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('rating')
                            ->label('Ocena')
                            ->options([
                                1 => '1 ⭐',
                                2 => '2 ⭐⭐',
                                3 => '3 ⭐⭐⭐',
                                4 => '4 ⭐⭐⭐⭐',
                                5 => '5 ⭐⭐⭐⭐⭐',
                            ])
                            ->default(5)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('motorcycle_id')
                            ->label('Motocykl (opcjonalne)')
                            ->relationship('motorcycle', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Motocykl, którego dotyczy opinia'),

                        Forms\Components\Textarea::make('content')
                            ->label('Treść opinii')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('published')
                            ->label('Opublikowany')
                            ->default(false),
                    ]),
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
                        $base = url('/api/motorent/testimonials');
                        if ($u?->isSuperAdmin()) {
                            return $base;
                        }
                        return $base . '?tenant_id=' . ($u?->tenant_id ?? \App\Modules\Core\Models\Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id') ?? '');
                    })
                    ->openUrlInNewTab()
                    ->color('info'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('Kolejność')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('author_name')
                    ->label('Autor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('content')
                    ->label('Treść')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Ocena')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 5 => 'success',
                        $state >= 4 => 'info',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state): string => str_repeat('⭐', $state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('motorcycle.name')
                    ->label('Motocykl')
                    ->searchable()
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
            ->defaultSort('order')
            ->filters([
                Tables\Filters\SelectFilter::make('rating')
                    ->label('Ocena')
                    ->options([
                        5 => '5 ⭐',
                        4 => '4 ⭐',
                        3 => '3 ⭐',
                        2 => '2 ⭐',
                        1 => '1 ⭐',
                    ])
                    ->native(false),

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
            'index' => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'edit' => Pages\EditTestimonial::route('/{record}/edit'),
        ];
    }
}
