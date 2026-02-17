<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Widget z ostatnimi zleceniami.
 * Widoczny tylko dla super admina.
 */
class LatestOrders extends BaseWidget
{
    protected static ?int $sort = 2;

    /**
     * Tylko super admin widzi ten widget.
     */
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Ostatnie zlecenia';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with(['customer', 'site'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Numer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->limit(40),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Klient'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'offer_sent',
                        'warning' => fn ($state) => in_array($state, ['accepted', 'in_progress']),
                        'primary' => 'delivered',
                        'success' => fn ($state) => in_array($state, ['paid', 'completed']),
                        'danger' => fn ($state) => str_starts_with($state, 'dispute') || $state === 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Szkic',
                        'offer_sent' => 'Oferta',
                        'accepted' => 'Przyjęte',
                        'in_progress' => 'W toku',
                        'delivered' => 'Dostarczone',
                        'paid' => 'Opłacone',
                        'completed' => 'Zakończone',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label('Cena')
                    ->money('PLN'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Utworzono')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Zobacz')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
