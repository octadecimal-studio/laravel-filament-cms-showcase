<?php

declare(strict_types=1);

namespace App\Modules\Content\Models\TwoWheels;

use App\Modules\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model ustawień strony MotoRent Demo (single type).
 *
 * @property string $id UUID
 * @property string $tenant_id UUID tenanta
 * @property string $site_title Tytuł strony
 * @property string $site_description Opis strony
 * @property string|null $about_us_content Treść sekcji "O nas" (HTML/Markdown)
 * @property string|null $regulamin_content Treść regulaminu (HTML)
 * @property string|null $polityka_prywatnosci_content Treść polityki prywatności (HTML)
 * @property string|null $logo_id UUID logo (Media)
 * @property string $contact_phone Telefon kontaktowy
 * @property string $contact_email Email kontaktowy
 * @property string $address Adres
 * @property string $opening_hours Godziny otwarcia
 * @property string $map_coordinates Współrzędne mapy (lat,lng)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\Content\Models\Media|null $logo
 */
final class SiteSetting extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<\Database\Factories\SiteSettingFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Nazwa tabeli.
     */
    protected $table = 'two_wheels_site_settings';

    /**
     * Atrybuty które można masowo przypisywać.
     *
     * @var list<string>
     */
    protected $fillable = [
        'site_title',
        'site_description',
        'about_us_content',
        'regulamin_content',
        'polityka_prywatnosci_content',
        'logo_id',
        'contact_phone',
        'contact_email',
        'address',
        'opening_hours',
        'map_coordinates',
        'google_analytics_code',
        'pricing_title',
        'pricing_subtitle',
        'location_title',
        'location_description',
        'reservation_form_type',
        'reservation_form_external_url',
        'reservation_notification_email',
        'company_data',
        'social_media',
    ];

    /**
     * Atrybuty rzutowane.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_data' => 'array',
            'social_media' => 'array',
        ];
    }

    /**
     * Relacja: Logo (Media).
     *
     * @return BelongsTo<\App\Modules\Content\Models\Media, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Content\Models\Media::class, 'logo_id')
            ->withoutGlobalScope(\App\Modules\Core\Scopes\TenantScope::class);
    }
}
