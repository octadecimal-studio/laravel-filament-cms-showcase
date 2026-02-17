<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model dla pluginów Filament.
 */
class Plugin extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'plugins';

    protected $fillable = [
        'name',
        'package',
        'class_name',
        'description',
        'version',
        'author',
        'homepage',
        'repository',
        'config',
        'is_installed',
        'is_enabled',
        'is_official',
        'category',
        'tags',
        'downloads_count',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'tags' => 'array',
            'is_installed' => 'boolean',
            'is_enabled' => 'boolean',
            'is_official' => 'boolean',
            'downloads_count' => 'integer',
            'installed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Sprawdza czy plugin jest dostępny (zainstalowany i włączony).
     */
    public function isAvailable(): bool
    {
        return $this->is_installed && $this->is_enabled;
    }

    /**
     * Pobiera instancję pluginu Filament.
     */
    public function getPluginInstance()
    {
        if (!$this->class_name) {
            return null;
        }

        // Sprawdź kompatybilność PRZED próbą załadowania klasy
        // NIE używamy class_exists() bo to może wywołać autoload i fatal error
        $service = app(\App\Modules\Plugins\Services\PluginService::class);
        $compatibility = $service->checkCompatibility($this->package, $this->class_name);
        
        if (!$compatibility['compatible']) {
            \Illuminate\Support\Facades\Log::warning("Plugin {$this->package} nie jest kompatybilny: " . $compatibility['message']);
            return null;
        }

        // Tylko teraz sprawdzamy czy klasa istnieje (po sprawdzeniu kompatybilności)
        // Używamy class_exists($class, false) - false oznacza że NIE wywołuje autoloadera
        // To zapobiega fatal error jeśli klasa wymaga brakujących zależności
        if (!class_exists($this->class_name, false)) {
            \Illuminate\Support\Facades\Log::warning("Klasa pluginu {$this->class_name} nie istnieje lub wymaga brakujących zależności");
            return null;
        }

        try {
            // Próbuj różne metody tworzenia instancji
            if (method_exists($this->class_name, 'make')) {
                return $this->class_name::make();
            }
            
            // Jeśli nie ma make(), spróbuj new
            return new $this->class_name();
        } catch (\Throwable $e) {
            // Używamy Throwable zamiast Exception aby złapać również Fatal Errors
            \Illuminate\Support\Facades\Log::error("Błąd tworzenia instancji pluginu {$this->class_name}: " . $e->getMessage());
            return null;
        }
    }
}
