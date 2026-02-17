<?php

declare(strict_types=1);

namespace App\Modules\Content\Traits;

use App\Modules\Content\Models\ContentVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Trait zapewniający wersjonowanie dla modeli.
 *
 * Automatycznie tworzy snapshoty modelu przy każdej zmianie.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContentVersion> $versions
 * @property-read ContentVersion|null $currentVersion
 *
 * @mixin Model
 *
 * @phpstan-ignore-next-line trait.unused
 */
trait HasContentVersions
{
    /**
     * Boot traitu - rejestruje eventy.
     */
    public static function bootHasContentVersions(): void
    {
        // Utwórz wersję przy tworzeniu modelu
        static::created(function (Model $model): void {
            /** @var Model&HasContentVersions $model */
            $model->createVersion('Initial version');
        });

        // Utwórz wersję przy aktualizacji modelu
        static::updated(function (Model $model): void {
            /** @var Model&HasContentVersions $model */
            if ($model->shouldCreateVersion()) {
                $model->createVersion('Updated');
            }
        });
    }

    /**
     * Relacja: Wersje modelu.
     *
     * @return MorphMany<ContentVersion>
     */
    public function versions(): MorphMany
    {
        return $this->morphMany(ContentVersion::class, 'versionable')
            ->orderBy('version', 'desc');
    }

    /**
     * Relacja: Bieżąca wersja.
     *
     * @return MorphMany<ContentVersion>
     */
    public function currentVersion(): MorphMany
    {
        return $this->versions()->where('is_current', true)->limit(1);
    }

    /**
     * Utwórz nową wersję modelu.
     */
    public function createVersion(?string $changeSummary = null): ContentVersion
    {
        // Pobierz poprzednią wersję
        /** @var ContentVersion|null $previousVersion */
        $previousVersion = $this->versions()->orderBy('version', 'desc')->first();

        $newVersionNumber = $previousVersion ? $previousVersion->version + 1 : 1;

        // Oznacz poprzednią wersję jako nieaktualną
        if ($previousVersion && $previousVersion->is_current) {
            $previousVersion->is_current = false;
            $previousVersion->save();
        }

        // Snapshot aktualnego stanu modelu
        $currentData = $this->toArray();

        // Oblicz zmiany (diff)
        $changes = $previousVersion
            ? $this->calculateChanges($previousVersion->data, $currentData)
            : null;

        /** @var User|null $user */
        $user = Auth::user();

        // Utwórz nową wersję
        /** @var ContentVersion $version */
        $version = $this->versions()->create([
            'version' => $newVersionNumber,
            'is_current' => true,
            'data' => $currentData,
            'changes' => $changes,
            'change_summary' => $changeSummary,
            'created_by' => $user?->id,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        return $version;
    }

    /**
     * Przywróć model do konkretnej wersji.
     */
    public function restoreToVersion(ContentVersion|int $version): bool
    {
        // Pobierz wersję
        $versionModel = $version instanceof ContentVersion
            ? $version
            : $this->versions()->where('version', $version)->firstOrFail();

        // Przywróć dane z wersji
        $this->fill($versionModel->data);
        $result = $this->save();

        // Oznacz tę wersję jako bieżącą
        if ($result) {
            $versionModel->markAsCurrent();
        }

        return $result;
    }

    /**
     * Sprawdź czy należy utworzyć nową wersję.
     *
     * Domyślnie tworzy wersję przy każdej zmianie.
     * Model może nadpisać tę metodę dla custom logic.
     */
    protected function shouldCreateVersion(): bool
    {
        return $this->isDirty() && ! $this->wasRecentlyCreated;
    }

    /**
     * Oblicz różnice między dwiema wersjami danych.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function calculateChanges(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if (! array_key_exists($key, $old) || $old[$key] !== $value) {
                $changes[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }
}
