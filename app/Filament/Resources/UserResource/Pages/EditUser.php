<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Pobierz rolę użytkownika
        $user = $this->record;
        $role = $user->roles->first();
        if ($role) {
            $data['role'] = $role->name;
        }

        // Ustaw email_verified_at jako boolean
        $data['email_verified_at'] = !empty($data['email_verified_at']);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ustaw email_verified_at jeśli checkbox był zaznaczony
        if (isset($data['email_verified_at']) && $data['email_verified_at']) {
            $data['email_verified_at'] = now();
        } else {
            $data['email_verified_at'] = null;
        }

        // Usuń role z danych (przypiszemy później)
        $role = $data['role'] ?? null;
        unset($data['role']);

        // Jeśli super_admin, przypisz do system tenant (Tenant 0)
        if (isset($data['is_super_admin']) && $data['is_super_admin']) {
            $systemTenant = \App\Modules\Core\Models\Tenant::getSystemTenant();
            if ($systemTenant) {
                $data['tenant_id'] = $systemTenant->id;
            }
        }

        // Zapisz rolę w sesji, aby użyć po zapisie
        session(['user_edit_role' => $role]);

        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->record;

        // Zaktualizuj rolę
        $role = session('user_edit_role');
        if ($role) {
            // Usuń wszystkie role i przypisz nową
            $user->syncRoles([$role]);
        }

        // Wyczyść sesję
        session()->forget('user_edit_role');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
