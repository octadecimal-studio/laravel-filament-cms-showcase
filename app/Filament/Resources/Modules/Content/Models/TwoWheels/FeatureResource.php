<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource\Pages;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource\RelationManagers;
use App\Modules\Content\Models\TwoWheels\Feature;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla zarządzania zaletami wypożyczalni.
 */
final class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Zalety';

    protected static ?string $modelLabel = 'Zaleta';

    protected static ?string $pluralModelLabel = 'Zalety';

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
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Ikona')
                    ->schema([
                        Forms\Components\Placeholder::make('current_icon_preview')
                            ->label('Aktualna ikona')
                            ->content(function (?Feature $record): Htmlable {
                                if (! $record || ! $record->icon) {
                                    return new HtmlString('<span class="text-gray-500">Brak ikony</span>');
                                }
                                $url = asset('storage/' . $record->icon->file_path);
                                return new HtmlString(
                                    '<img src="' . $url . '" alt="' . e($record->icon->file_name) . '" ' .
                                    'class="max-h-24 rounded-lg shadow-md object-contain" />'
                                );
                            })
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('new_icon')
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Wgraj ikonę' : 'Podmień ikonę')
                            ->helperText('Wgraj ikonę lub użyj edytora do kadrowania. Obsługiwane: JPG, PNG, WebP.')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('features/icons')
                            ->visibility('public')
                            ->imagePreviewHeight('120')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '1:1',
                                '4:3',
                                '3:2',
                                '16:9',
                            ])
                            ->imageEditorEmptyFillColor('#ffffff')
                            ->imageEditorViewportWidth(400)
                            ->imageEditorViewportHeight(400)
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('icon_id'),
                    ]),

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
                        $base = url('/api/motorent/features');
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

                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Opis')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\ImageColumn::make('icon.file_path')
                    ->label('Ikona')
                    ->height(30)
                    ->width(30)
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
            'index' => Pages\ListFeatures::route('/'),
            'create' => Pages\CreateFeature::route('/create'),
            'edit' => Pages\EditFeature::route('/{record}/edit'),
        ];
    }
}
