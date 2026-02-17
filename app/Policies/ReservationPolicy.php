<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy dla rezerwacji (Reservation).
 *
 * Klient widzi i zarządza rezerwacjami swoich stron.
 */
class ReservationPolicy extends BasePolicy
{
    protected string $permissionPrefix = 'reservations';

    /**
     * Klient widzi tylko rezerwacje ze swoich stron.
     */
    public function view(User $user, Model $model): bool
    {
        if ($user->hasRole('client')) {
            $siteIds = $user->sites()->pluck('sites.id')->toArray();
            return in_array($model->site_id ?? null, $siteIds);
        }

        return parent::view($user, $model);
    }

    /**
     * Klient może edytować rezerwacje swoich stron.
     */
    public function update(User $user, Model $model): bool
    {
        if ($user->hasRole('client')) {
            $siteIds = $user->sites()->pluck('sites.id')->toArray();
            return in_array($model->site_id ?? null, $siteIds);
        }

        return parent::update($user, $model);
    }

    /**
     * Klient może usuwać rezerwacje swoich stron.
     */
    public function delete(User $user, Model $model): bool
    {
        if ($user->hasRole('client')) {
            $siteIds = $user->sites()->pluck('sites.id')->toArray();
            return in_array($model->site_id ?? null, $siteIds);
        }

        return parent::delete($user, $model);
    }
}
