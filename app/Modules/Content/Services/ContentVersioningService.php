<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use App\Models\User;
use App\Modules\Content\Models\ContentVersion;
use App\Modules\Content\Models\SiteContent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Serwis zarządzający wersjonowaniem treści.
 *
 * Obsługuje tworzenie wersji, porównywanie i historię zmian.
 */
final class ContentVersioningService
{
    /**
     * Tworzy nową wersję kontentu.
     *
     * @param SiteContent $content Kontent
     * @param array<string, mixed> $data Dane do zapisania w wersji
     * @param User|null $user Użytkownik tworzący
     * @param string|null $changeSummary Opis zmian
     * @return ContentVersion
     */
    public function createVersion(
        SiteContent $content,
        array $data,
        ?User $user = null,
        ?string $changeSummary = null
    ): ContentVersion {
        return DB::transaction(function () use ($content, $data, $user, $changeSummary) {
            // Pobierz poprzednią wersję
            $previousVersion = $this->getCurrentVersion($content);
            $newVersionNumber = $previousVersion ? $previousVersion->version + 1 : 1;

            // Oblicz zmiany
            $changes = $previousVersion
                ? $this->calculateChanges($previousVersion->data, $data)
                : null;

            // Oznacz poprzednią jako nieaktualną
            if ($previousVersion?->is_current) {
                $previousVersion->update(['is_current' => false]);
            }

            // Utwórz nową wersję
            return ContentVersion::create([
                'tenant_id' => $content->tenant_id,
                'versionable_id' => $content->id,
                'versionable_type' => $content->getMorphClass(),
                'version' => $newVersionNumber,
                'is_current' => true,
                'data' => $data,
                'changes' => $changes,
                'change_summary' => $changeSummary ?? 'Version ' . $newVersionNumber,
                'created_by' => $user?->id,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        });
    }

    /**
     * Pobiera bieżącą (aktywną) wersję kontentu.
     *
     * @param SiteContent $content Kontent
     * @return ContentVersion|null
     */
    public function getCurrentVersion(SiteContent $content): ?ContentVersion
    {
        return ContentVersion::query()
            ->where('versionable_id', $content->id)
            ->where('versionable_type', $content->getMorphClass())
            ->where('is_current', true)
            ->first();
    }

    /**
     * Pobiera historię wersji kontentu.
     *
     * @param SiteContent $content Kontent
     * @param int $limit Limit wyników
     * @return Collection<int, ContentVersion>
     */
    public function getVersionHistory(SiteContent $content, int $limit = 50): Collection
    {
        return ContentVersion::query()
            ->where('versionable_id', $content->id)
            ->where('versionable_type', $content->getMorphClass())
            ->with('author')
            ->orderByDesc('version')
            ->limit($limit)
            ->get();
    }

    /**
     * Pobiera konkretną wersję.
     *
     * @param SiteContent $content Kontent
     * @param int $versionNumber Numer wersji
     * @return ContentVersion|null
     */
    public function getVersion(SiteContent $content, int $versionNumber): ?ContentVersion
    {
        return ContentVersion::query()
            ->where('versionable_id', $content->id)
            ->where('versionable_type', $content->getMorphClass())
            ->where('version', $versionNumber)
            ->first();
    }

    /**
     * Porównuje dwie wersje kontentu.
     *
     * @param ContentVersion $v1 Pierwsza wersja
     * @param ContentVersion $v2 Druga wersja
     * @return array{added: array<string, mixed>, removed: array<string, mixed>, changed: array<string, array{old: mixed, new: mixed}>}
     */
    public function compareVersions(ContentVersion $v1, ContentVersion $v2): array
    {
        $data1 = $v1->data;
        $data2 = $v2->data;

        $added = [];
        $removed = [];
        $changed = [];

        // Sprawdź nowe i zmienione
        foreach ($data2 as $key => $value) {
            if (! array_key_exists($key, $data1)) {
                $added[$key] = $value;
            } elseif ($data1[$key] !== $value) {
                $changed[$key] = [
                    'old' => $data1[$key],
                    'new' => $value,
                ];
            }
        }

        // Sprawdź usunięte
        foreach ($data1 as $key => $value) {
            if (! array_key_exists($key, $data2)) {
                $removed[$key] = $value;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * Przywraca kontent do konkretnej wersji.
     *
     * @param SiteContent $content Kontent
     * @param ContentVersion $version Wersja do przywrócenia
     * @param User|null $user Użytkownik
     * @return ContentVersion Nowa wersja z przywróconymi danymi
     */
    public function restoreVersion(
        SiteContent $content,
        ContentVersion $version,
        ?User $user = null
    ): ContentVersion {
        // Utwórz nową wersję z danymi ze starej
        return $this->createVersion(
            $content,
            $version->data,
            $user,
            "Restored from version {$version->version}"
        );
    }

    /**
     * Liczy wersje dla kontentu.
     *
     * @param SiteContent $content Kontent
     * @return int
     */
    public function countVersions(SiteContent $content): int
    {
        return ContentVersion::query()
            ->where('versionable_id', $content->id)
            ->where('versionable_type', $content->getMorphClass())
            ->count();
    }

    /**
     * Usuwa stare wersje, zachowując N najnowszych.
     *
     * @param SiteContent $content Kontent
     * @param int $keepCount Ile wersji zachować
     * @return int Liczba usuniętych wersji
     */
    public function pruneOldVersions(SiteContent $content, int $keepCount = 50): int
    {
        $versionsToDelete = ContentVersion::query()
            ->where('versionable_id', $content->id)
            ->where('versionable_type', $content->getMorphClass())
            ->where('is_current', false)
            ->orderByDesc('version')
            ->skip($keepCount)
            ->pluck('id');

        if ($versionsToDelete->isEmpty()) {
            return 0;
        }

        return ContentVersion::whereIn('id', $versionsToDelete)->delete();
    }

    /**
     * Oblicza różnice między dwiema wersjami danych.
     *
     * @param array<string, mixed> $old Stare dane
     * @param array<string, mixed> $new Nowe dane
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function calculateChanges(array $old, array $new): array
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

        // Wykryj usunięte klucze
        foreach ($old as $key => $value) {
            if (! array_key_exists($key, $new)) {
                $changes[$key] = [
                    'old' => $value,
                    'new' => null,
                ];
            }
        }

        return $changes;
    }
}
