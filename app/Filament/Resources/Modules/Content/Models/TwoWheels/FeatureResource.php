<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Scopes\TenantScope;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Action;
use App\Modules\Core\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource\Pages\ListFeatures;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource\Pages\CreateFeature;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\FeatureResource\Pages\EditFeature;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Zalety';

    protected static ?string $modelLabel = 'Zaleta';

    protected static ?string $pluralModelLabel = 'Zalety';

    protected static ?int $navigationSort = 10;

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
                    ])
                    ->columns(2),

                Section::make('Ikona')
                    ->schema([
                        Placeholder::make('current_icon_preview')
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

                        FileUpload::make('new_icon')
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

                        Hidden::make('icon_id'),
                    ]),

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
                        $base = url('/api/motorent/features');
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

                TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Opis')
                    ->limit(50)
                    ->toggleable(),

                ImageColumn::make('icon.file_path')
                    ->label('Ikona')
                    ->height(30)
                    ->width(30)
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
            'index' => ListFeatures::route('/'),
            'create' => CreateFeature::route('/create'),
            'edit' => EditFeature::route('/{record}/edit'),
        ];
    }
}
