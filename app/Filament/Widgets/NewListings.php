<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\ListingResource;
use App\Models\Listing;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Widget z nowymi ogłoszeniami.
 * Widoczny tylko dla super admina.
 */
class NewListings extends BaseWidget
{
    protected static ?int $sort = 4;

    /**
     * Tylko super admin widzi ten widget.
     */
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    protected int | string | array $columnSpan = 1;

    protected static ?string $heading = 'Nowe ogłoszenia';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Listing::query()
                    ->whereIn('status', ['new', 'reviewing'])
                    ->orderBy('found_at', 'desc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->limit(35),

                Tables\Columns\BadgeColumn::make('platform')
                    ->label('Platforma')
                    ->colors([
                        'success' => 'useme',
                        'info' => 'oferteo',
                        'warning' => 'fixly',
                    ]),

                Tables\Columns\TextColumn::make('budget_range')
                    ->label('Budżet')
                    ->getStateUsing(fn ($record) => $record->budget_range),

                Tables\Columns\BadgeColumn::make('priority')
                    ->label('Priorytet')
                    ->colors([
                        'gray' => 'low',
                        'warning' => 'medium',
                        'danger' => 'high',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Zobacz')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Listing $record): string => ListingResource::getUrl('view', ['record' => $record])),

                Tables\Actions\Action::make('visit')
                    ->label('Otwórz')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
