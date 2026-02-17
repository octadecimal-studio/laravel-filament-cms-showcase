<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Password;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ustaw email_verified_at jeśli checkbox był zaznaczony
        if (isset($data['email_verified_at']) && $data['email_verified_at']) {
            $data['email_verified_at'] = now();
        } else {
            $data['email_verified_at'] = null;
        }

        // Usuń send_invitation z danych (nie zapisujemy tego w bazie)
        $sendInvitation = $data['send_invitation'] ?? false;
        unset($data['send_invitation']);

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

        // Zapisz send_invitation i role w sesji, aby użyć po utworzeniu
        session([
            'user_invitation_send' => $sendInvitation,
            'user_invitation_role' => $role,
        ]);

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->record;

        // Przypisz rolę
        $role = session('user_invitation_role');
        if ($role) {
            $user->assignRole($role);
        }

        // Wyślij email z zaproszeniem jeśli zaznaczono
        $sendInvitation = session('user_invitation_send', false);
        if ($sendInvitation) {
            try {
                // Użyj Laravel Password Reset do wysłania linku do ustawienia hasła
                $status = Password::sendResetLink(['email' => $user->email]);

                if ($status === Password::RESET_LINK_SENT) {
                    Notification::make()
                        ->title('Email z zaproszeniem został wysłany')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Nie udało się wysłać emaila z zaproszeniem')
                        ->warning()
                        ->send();
                }
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Błąd podczas wysyłania emaila: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
        }

        // Wyczyść sesję
        session()->forget(['user_invitation_send', 'user_invitation_role']);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
