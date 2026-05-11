<?php

declare(strict_types=1);

namespace App\Observers;

use App\Mail\AdminRentalNotification;
use App\Mail\RentalReceived;
use App\Modules\Content\Models\TwoWheels\SiteSetting;
use App\Modules\Core\Scopes\TenantScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Octadecimal\Rental\Models\Rental;

class RentalObserver
{
    public function created(Rental $rental): void
    {
        $this->notifyAdmin($rental);
        $this->notifyCustomer($rental);
    }

    private function notifyAdmin(Rental $rental): void
    {
        try {
            $setting = SiteSetting::withoutGlobalScope(TenantScope::class)->first();
            $adminEmail = $setting?->reservation_notification_email;

            if (empty($adminEmail)) {
                return;
            }

            Mail::to($adminEmail)->send(new AdminRentalNotification($rental));
        } catch (\Throwable $e) {
            Log::warning('AdminRentalNotification mail failed', [
                'rental_id' => $rental->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyCustomer(Rental $rental): void
    {
        if (empty($rental->email)) {
            return;
        }

        try {
            Mail::to($rental->email)->send(new RentalReceived($rental));
        } catch (\Throwable $e) {
            Log::warning('RentalReceived mail failed', [
                'rental_id' => $rental->id,
                'email' => $rental->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
