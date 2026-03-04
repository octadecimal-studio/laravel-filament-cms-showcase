<?php

declare(strict_types=1);

namespace App\Filament\Resources\Modules\Content\Models\TwoWheels;

use App\Filament\Resources\Modules\Content\Models\TwoWheels\SiteSettingResource\Pages;
use App\Filament\Resources\Modules\Content\Models\TwoWheels\SiteSettingResource\RelationManagers;
use App\Modules\Content\Models\TwoWheels\SiteSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

/**
 * Filament Resource dla zarządzania ustawieniami strony.
 */
final class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Ustawienia strony';

    protected static ?string $modelLabel = 'Ustawienie strony';

    protected static ?string $pluralModelLabel = 'Ustawienia strony';

    protected static ?int $navigationSort = 70;

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
                        Forms\Components\TextInput::make('site_title')
                            ->label('Tytuł strony')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('site_description')
                            ->label('Opis strony')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Sekcja "O nas"')
                    ->description('Treść wyświetlana w sekcji "O nas" na stronie głównej. Możesz używać formatowania HTML.')
                    ->schema([
                        Forms\Components\RichEditor::make('about_us_content')
                            ->label('Treść sekcji "O nas"')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'h2',
                                'h3',
                                'bulletList',
                                'orderedList',
                                'blockquote',
                                'link',
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Regulamin')
                    ->description('Treść regulaminu wyświetlana w sekcji na stronie głównej.')
                    ->schema([
                        Forms\Components\RichEditor::make('regulamin_content')
                            ->label('Treść regulaminu')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'link',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Polityka prywatności')
                    ->description('Treść polityki prywatności wyświetlana w sekcji na stronie głównej.')
                    ->schema([
                        Forms\Components\RichEditor::make('polityka_prywatnosci_content')
                            ->label('Treść polityki prywatności')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'link',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Forms\Components\Section::make('Logo')
                    ->schema([
                        Forms\Components\Placeholder::make('current_logo_preview')
                            ->label('Aktualne logo')
                            ->content(function (?SiteSetting $record): Htmlable {
                                if (! $record || ! $record->logo) {
                                    return new HtmlString('<span class="text-gray-500">Brak logo</span>');
                                }
                                $url = asset('storage/' . $record->logo->file_path);
                                return new HtmlString(
                                    '<img src="' . $url . '" alt="' . e($record->logo->file_name) . '" ' .
                                    'class="max-h-32 rounded-lg shadow-md object-contain" />'
                                );
                            })
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('new_logo')
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Wgraj logo' : 'Podmień logo')
                            ->helperText('Wgraj logo lub użyj edytora do kadrowania. Obsługiwane: JPG, PNG, WebP.')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory('site-settings/logos')
                            ->visibility('public')
                            ->imagePreviewHeight('150')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '3:2',
                                '1:1',
                            ])
                            ->imageEditorEmptyFillColor('#ffffff')
                            ->imageEditorViewportWidth(800)
                            ->imageEditorViewportHeight(450)
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('logo_id'),
                    ]),

                Forms\Components\Section::make('Social Media')
                    ->description('Linki do profili w mediach społecznościowych. Zostawiaj puste, jeśli nie chcesz wyświetlać danej platformy.')
                    ->schema([
                        Forms\Components\TextInput::make('social_media.facebook')
                            ->label('Facebook')
                            ->url()
                            ->placeholder('https://facebook.com/twoj-profil')
                            ->prefixIcon('heroicon-o-globe-alt')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('social_media.instagram')
                            ->label('Instagram')
                            ->url()
                            ->placeholder('https://instagram.com/twoj-profil')
                            ->prefixIcon('heroicon-o-camera')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('social_media.tiktok')
                            ->label('TikTok')
                            ->url()
                            ->placeholder('https://tiktok.com/@twoj-profil')
                            ->prefixIcon('heroicon-o-play')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('social_media.youtube')
                            ->label('YouTube')
                            ->url()
                            ->placeholder('https://youtube.com/@twoj-kanal')
                            ->prefixIcon('heroicon-o-video-camera')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('social_media.linkedin')
                            ->label('LinkedIn')
                            ->url()
                            ->placeholder('https://linkedin.com/company/twoja-firma')
                            ->prefixIcon('heroicon-o-briefcase')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Google Analytics')
                    ->description('Wklej kod śledzenia Google Analytics (np. tag gtag.js).')
                    ->schema([
                        Forms\Components\Textarea::make('google_analytics_code')
                            ->label('Kod Google Analytics')
                            ->rows(5)
                            ->placeholder('<!-- Google tag (gtag.js) -->...')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Tables\Actions\Action::make('view_api')
                    ->label('Zobacz API')
                    ->icon('heroicon-o-code-bracket')
                    ->url(fn () => url('/api/motorent/site-setting?tenant_id=' . (auth()->user()?->tenant_id ?? \App\Modules\Core\Models\Tenant::where('slug', 'demo-studio')->where('is_active', true)->value('id') ?? '')))
                    ->openUrlInNewTab()
                    ->color('info'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('site_title')
                    ->label('Tytuł strony')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('site_description')
                    ->label('Opis')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\ImageColumn::make('logo.file_path')
                    ->label('Logo')
                    ->height(40)
                    ->width(40)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Zaktualizowano')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
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
            'index' => Pages\ListSiteSettings::route('/'),
            'create' => Pages\CreateSiteSetting::route('/create'),
            'edit' => Pages\EditSiteSetting::route('/{record}/edit'),
        ];
    }
}
