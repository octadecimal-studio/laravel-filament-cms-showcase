<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\CorrectionResource;
use App\Models\Correction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Widget z oczekującymi poprawkami.
 * Widoczny tylko dla super admina.
 */
class PendingCorrections extends BaseWidget
{
    protected static ?int $sort = 3;

    /**
     * Tylko super admin widzi ten widget.
     */
    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    protected int | string | array $columnSpan = 1;

    protected static ?string $heading = 'Oczekujące poprawki';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Correction::query()
                    ->with(['site', 'order'])
                    ->whereIn('status', ['reported', 'accepted', 'in_progress'])
                    ->orderBy('reported_at', 'desc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Tytuł')
                    ->limit(30),

                Tables\Columns\TextColumn::make('site.name')
                    ->label('Strona'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'reported',
                        'info' => 'accepted',
                        'primary' => 'in_progress',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'reported' => 'Zgłoszona',
                        'accepted' => 'Zaakceptowana',
                        'in_progress' => 'W toku',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_free')
                    ->label('Darmowa')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Zobacz')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Correction $record): string => CorrectionResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
