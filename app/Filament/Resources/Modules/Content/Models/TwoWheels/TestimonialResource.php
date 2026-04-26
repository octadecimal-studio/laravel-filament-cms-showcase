<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Scopes\TenantScope;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Actions\Action;
use App\Modules\Core\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\TestimonialResource\Pages\ListTestimonials;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\TestimonialResource\Pages\CreateTestimonial;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\TestimonialResource\Pages\EditTestimonial;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Opinie';

    protected static ?string $modelLabel = 'Opinia';

    protected static ?string $pluralModelLabel = 'Opinie';

    protected static ?int $navigationSort = 80;

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
                        TextInput::make('order')
                            ->label('Kolejność')
                            ->numeric()
                            ->default(0)
                            ->required(),

                        TextInput::make('author_name')
                            ->label('Autor')
                            ->required()
                            ->maxLength(255),

                        Select::make('rating')
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

                        Select::make('motorcycle_id')
                            ->label('Motocykl (opcjonalne)')
                            ->relationship('motorcycle', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Motocykl, którego dotyczy opinia'),

                        Textarea::make('content')
                            ->label('Treść opinii')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Toggle::make('published')
                            ->label('Opublikowany')
                            ->default(false),
                    ]),
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
                        $base = url('/api/motorent/testimonials');
                        if ($u?->isSuperAdmin()) {
                            return $base;
                        }
                        return $base . '?tenant_id=' . ($u?->tenant_id ?? Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id') ?? '');
                    })
                    ->openUrlInNewTab()
                    ->color('info'),
            ])
            ->columns([
                TextColumn::make('order')
                    ->label('Kolejność')
                    ->sortable()
                    ->badge(),

                TextColumn::make('author_name')
                    ->label('Autor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('content')
                    ->label('Treść')
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('rating')
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

                TextColumn::make('motorcycle.name')
                    ->label('Motocykl')
                    ->searchable()
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
            ->defaultSort('order')
            ->filters([
                SelectFilter::make('rating')
                    ->label('Ocena')
                    ->options([
                        5 => '5 ⭐',
                        4 => '4 ⭐',
                        3 => '3 ⭐',
                        2 => '2 ⭐',
                        1 => '1 ⭐',
                    ])
                    ->native(false),

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
            'index' => ListTestimonials::route('/'),
            'create' => CreateTestimonial::route('/create'),
            'edit' => EditTestimonial::route('/{record}/edit'),
        ];
    }
}
