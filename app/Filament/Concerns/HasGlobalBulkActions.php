<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Tables;
use Filament\Tables\Table;

/**
 * Trait dodający globalne Bulk Actions do wszystkich Filament Resources.
 */
trait HasGlobalBulkActions
{
    /**
     * Konfiguruj bulk actions dla tabeli.
     */
    protected static function configureBulkActions(Table $table): Table
    {
        // Sprawdź czy już są bulk actions
        $existingBulkActions = $table->getBulkActions();
        
        // Jeśli już są, nie nadpisuj
        if (! empty($existingBulkActions)) {
            return $table;
        }
        
        // Dodaj podstawowe bulk actions
        return $table->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
            ]),
        ]);
    }
}
