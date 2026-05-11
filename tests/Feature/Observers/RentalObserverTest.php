<?php

declare(strict_types=1);

namespace Tests\Feature\Observers;

use App\Mail\AdminRentalNotification;
use App\Mail\RentalReceived;
use App\Modules\Core\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Octadecimal\Rental\Models\Rental;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RentalObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeSiteSetting(string $notificationEmail = 'biuro@example.com'): void
    {
        $tenant = Tenant::factory()->create();

        DB::table('two_wheels_site_settings')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'site_title' => 'MotoRent Demo',
            'reservation_notification_email' => $notificationEmail,
            'contact_email' => 'contact@example.com',
            'contact_phone' => '+48 123 456 789',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeRental(array $overrides = []): Rental
    {
        return Rental::factory()->create(array_merge([
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.com',
            'phone' => '+48 600 000 001',
        ], $overrides));
    }

    #[Test]
    public function it_sends_admin_notification_when_rental_is_created(): void
    {
        $this->makeSiteSetting('biuro@example.com');

        $this->makeRental(['email' => 'klient@example.com']);

        Mail::assertSent(AdminRentalNotification::class, function ($mail) {
            return $mail->hasTo('biuro@example.com');
        });
    }

    #[Test]
    public function it_sends_customer_receipt_when_rental_is_created(): void
    {
        $this->makeSiteSetting();

        $this->makeRental(['email' => 'klient@example.com']);

        Mail::assertSent(RentalReceived::class, function ($mail) {
            return $mail->hasTo('klient@example.com');
        });
    }

    #[Test]
    public function it_skips_admin_notification_when_notification_email_is_empty(): void
    {
        $this->makeSiteSetting('');

        $this->makeRental();

        Mail::assertNotSent(AdminRentalNotification::class);
    }

    #[Test]
    public function it_skips_admin_notification_when_site_setting_does_not_exist(): void
    {
        $this->makeRental();

        Mail::assertNotSent(AdminRentalNotification::class);
    }

    #[Test]
    public function it_skips_customer_receipt_when_rental_has_no_email(): void
    {
        $this->makeSiteSetting();

        $this->makeRental(['email' => '']);

        Mail::assertNotSent(RentalReceived::class);
    }

    #[Test]
    public function rental_is_persisted_and_both_mails_are_sent_on_happy_path(): void
    {
        $this->makeSiteSetting('biuro@example.com');

        $rental = $this->makeRental(['email' => 'klient@example.com']);

        $this->assertDatabaseHas('rentals', ['id' => $rental->id]);
        Mail::assertSent(AdminRentalNotification::class);
        Mail::assertSent(RentalReceived::class);
    }
}
