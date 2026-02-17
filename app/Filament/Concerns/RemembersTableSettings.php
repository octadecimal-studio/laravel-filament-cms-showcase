<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Tables\Table;
/**
 * Trait zapamiętujący ustawienia tabeli w sesji.
 * 
 * Zapamiętuje: sortowanie, filtry, paginację, widoczność kolumn.
 */
trait RemembersTableSettings
{
    /**
     * Konfiguruj zapamiętywanie ustawień tabeli.
     */
    protected static function configureTableSettings(Table $table): Table
    {
        // Zapamiętaj sortowanie (automatyczny klucz sesji)
        $table->persistSortInSession();
        
        // Zapamiętaj filtry (automatyczny klucz sesji)
        $table->persistFiltersInSession();
        
        // Zapamiętaj wyszukiwanie (automatyczny klucz sesji)
        $table->persistSearchInSession();
        
        // Zapamiętaj wyszukiwanie w kolumnach (automatyczny klucz sesji)
        $table->persistColumnSearchesInSession();

        return $table;
    }
}
