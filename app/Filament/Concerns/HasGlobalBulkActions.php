<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
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
        return $table->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
            ]),
        ]);
    }
}
