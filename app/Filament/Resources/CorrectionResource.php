<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Concerns\HasNavigationPermission;
use App\Filament\Resources\CorrectionResource\Pages;
use App\Models\Correction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource dla poprawek.
 */
class CorrectionResource extends Resource
{
    use HasNavigationPermission;

    /**
     * Prefix uprawnień dla tego zasobu.
     */
    protected static string $permissionPrefix = 'corrections';
    protected static ?string $model = Correction::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Poprawki';

    protected static ?string $modelLabel = 'Poprawka';

    protected static ?string $pluralModelLabel = 'Poprawki';

    protected static ?string $navigationGroup = 'Sprzedaż';

    protected static ?int $navigationSort = 3;

    // Widoczność w nawigacji jest zarządzana przez HasNavigationPermission trait
    // na podstawie uprawnienia 'corrections.view_any'

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informacje podstawowe')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Tytuł')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('order_id')
                            ->label('Zlecenie')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('site_id')
                            ->label('Strona')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'reported' => 'Zgłoszona',
                                'accepted' => 'Zaakceptowana',
                                'rejected' => 'Odrzucona',
                                'in_progress' => 'W realizacji',
                                'done' => 'Wykonana',
                                'verified' => 'Zweryfikowana',
                                'deployed' => 'Wdrożona',
                            ])
                            ->default('reported')
                            ->required(),

                        Forms\Components\Toggle::make('is_free')
                            ->label('Darmowa poprawka')
                            ->default(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Szczegóły')
                    ->schema([
                        Forms\Components\RichEditor::make('description')
                            ->label('Opis')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('page_url')
                            ->label('URL strony')
                            ->url()
                            ->maxLength(500),

                        Forms\Components\TextInput::make('estimated_price')
                            ->label('Szacowana cena')
                            ->numeric()
                            ->prefix('PLN')
                            ->visible(fn (callable $get) => !$get('is_free')),
                    ]),

                Forms\Components\Section::make('Przypisanie')
                    ->schema([
                        Forms\Components\Select::make('assigned_to')
                            ->label('Przypisane do')
                            ->relationship('assignedTo', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('reported_by')
                            ->label('Zgłoszone przez')
                            ->relationship('reportedBy', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Terminy')
                    ->schema([
                        Forms\Components\DateTimePicker::make('reported_at')
                            ->label('Zgłoszona'),

                        Forms\Components\DateTimePicker::make('accepted_at')
                            ->label('Zaakceptowana'),

                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('Wykonana'),

                        Forms\Components\DateTimePicker::make('verified_at')
                            ->label('Zweryfikowana'),

                        Forms\Components\DateTimePicker::make('deployed_at')
                            ->label('Wdrożona'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Odrzucenie')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Powód odrzucenia')
                            ->rows(3),
                    ])
                    ->visible(fn (callable $get) => $get('status') === 'rejected')
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->title),

                Tables\Columns\TextColumn::make('site.name')
                    ->label('Strona')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Zlecenie')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'reported',
                        'info' => 'accepted',
                        'danger' => 'rejected',
                        'primary' => 'in_progress',
                        'success' => fn ($state) => in_array($state, ['done', 'verified', 'deployed']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'reported' => 'Zgłoszona',
                        'accepted' => 'Zaakceptowana',
                        'rejected' => 'Odrzucona',
                        'in_progress' => 'W toku',
                        'done' => 'Wykonana',
                        'verified' => 'Zweryfikowana',
                        'deployed' => 'Wdrożona',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_free')
                    ->label('Darmowa')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-currency-dollar')
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('estimated_price')
                    ->label('Cena')
                    ->money('PLN')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Przypisane')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reported_at')
                    ->label('Zgłoszona')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options([
                        'reported' => 'Zgłoszona',
                        'accepted' => 'Zaakceptowana',
                        'rejected' => 'Odrzucona',
                        'in_progress' => 'W realizacji',
                        'done' => 'Wykonana',
                        'verified' => 'Zweryfikowana',
                        'deployed' => 'Wdrożona',
                    ]),

                Tables\Filters\TernaryFilter::make('is_free')
                    ->label('Darmowa'),

                Tables\Filters\SelectFilter::make('site_id')
                    ->label('Strona')
                    ->relationship('site', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('assigned_to')
                    ->label('Przypisane do')
                    ->relationship('assignedTo', 'name'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('accept')
                    ->label('Zaakceptuj')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'reported')
                    ->action(fn ($record) => $record->update([
                        'status' => 'accepted',
                        'accepted_at' => now(),
                    ])),

                Tables\Actions\Action::make('start')
                    ->label('Rozpocznij')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->visible(fn ($record) => $record->status === 'accepted')
                    ->action(fn ($record) => $record->update(['status' => 'in_progress'])),

                Tables\Actions\Action::make('complete')
                    ->label('Zakończ')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'in_progress')
                    ->action(fn ($record) => $record->update([
                        'status' => 'done',
                        'completed_at' => now(),
                    ])),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('reported_at', 'desc');
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
            'index' => Pages\ListCorrections::route('/'),
            'create' => Pages\CreateCorrection::route('/create'),
            'view' => Pages\ViewCorrection::route('/{record}'),
            'edit' => Pages\EditCorrection::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        // Klient widzi tylko poprawki swoich stron
        $user = auth()->user();
        if ($user && $user->hasRole('client')) {
            $siteIds = $user->sites()->pluck('sites.id')->toArray();
            $query->whereIn('site_id', $siteIds);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::whereIn('status', ['reported', 'accepted', 'in_progress'])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
