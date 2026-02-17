<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Correction;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\SiteEnvironment;
use App\Models\SpecTemplate;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder z przykładowym workflow CMS - od ogłoszenia do faktury.
 */
class CmsWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Tworzę przykładowe dane workflow CMS...');

        // 1. Admin/Developer
        $admin = $this->createAdmin();

        // 2. Listings (ogłoszenia)
        $listings = $this->createListings();

        // 3. Spec Templates
        $specTemplates = $this->createSpecTemplates($listings);

        // 4. Customers
        $customers = $this->createCustomers($listings);

        // 5. Sites
        $sites = $this->createSites($customers);

        // 6. Orders (pełny workflow)
        $orders = $this->createOrders($customers, $sites, $listings, $specTemplates);

        // 7. Site Users (klienci z dostępem)
        $this->createSiteUsers($sites, $customers);

        // 8. Site Domains & Environments
        $this->createSiteInfrastructure($sites);

        // 9. Corrections (poprawki)
        $corrections = $this->createCorrections($orders, $sites, $customers);

        // 10. Invoices & Payments
        $this->createInvoicesAndPayments($customers, $orders);

        // 11. Time Entries
        $this->createTimeEntries($admin, $listings, $orders, $sites, $corrections);

        $this->command->info('Dane przykładowe utworzone pomyślnie!');
    }

    private function createAdmin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@octadecimal.studio'],
            [
                'name' => 'Piotr Adamczyk',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    private function createListings(): array
    {
        $listings = [];

        // Listing 1: Wygrany
        $listings['won'] = Listing::create([
            'platform' => 'useme',
            'external_id' => '123456',
            'url' => 'https://useme.com/pl/jobs/123456',
            'title' => 'Strona www dla firmy budowlanej',
            'description' => 'Potrzebuję prostej, nowoczesnej strony dla mojej firmy budowlanej. 4-5 podstron, formularz kontaktowy, galeria realizacji.',
            'budget_min' => 2000,
            'budget_max' => 3500,
            'currency' => 'PLN',
            'deadline' => now()->addDays(14),
            'client_name' => 'budowlaniec123',
            'client_location' => 'Warszawa',
            'status' => 'won',
            'priority' => 'high',
            'notes' => 'Dobry budżet, jasny opis, klient responsywny',
            'found_at' => now()->subDays(10),
        ]);

        // Listing 2: W trakcie - spec ready
        $listings['spec_ready'] = Listing::create([
            'platform' => 'useme',
            'external_id' => '234567',
            'url' => 'https://useme.com/pl/jobs/234567',
            'title' => 'Landing page dla gabinetu kosmetycznego',
            'description' => 'Szukam wykonawcy do stworzenia landing page dla mojego gabinetu. Ważna jest estetyka i SEO.',
            'budget_min' => 1500,
            'budget_max' => 2500,
            'currency' => 'PLN',
            'deadline' => now()->addDays(7),
            'client_name' => 'beauty_spa',
            'client_location' => 'Kraków',
            'status' => 'spec_ready',
            'priority' => 'medium',
            'notes' => 'Przygotowany szablon, czekam na odpowiedź',
            'found_at' => now()->subDays(3),
        ]);

        // Listing 3: Nowy
        $listings['new'] = Listing::create([
            'platform' => 'oferteo',
            'external_id' => 'OF-789',
            'url' => 'https://oferteo.pl/zlecenie/789',
            'title' => 'Strona dla restauracji z menu online',
            'description' => 'Restauracja włoska potrzebuje strony z menu, możliwością rezerwacji online, galerią.',
            'budget_min' => 3000,
            'budget_max' => 5000,
            'currency' => 'PLN',
            'deadline' => now()->addDays(21),
            'client_name' => 'LaTrattoria',
            'client_location' => 'Gdańsk',
            'status' => 'new',
            'priority' => 'high',
            'notes' => 'Ciekawy projekt, duży budżet',
            'found_at' => now()->subHours(5),
        ]);

        // Listing 4: Przegrany
        $listings['lost'] = Listing::create([
            'platform' => 'useme',
            'external_id' => '111222',
            'url' => 'https://useme.com/pl/jobs/111222',
            'title' => 'Prosta wizytówka dla fotografa',
            'description' => 'Portfolio fotografa ślubnego.',
            'budget_min' => 800,
            'budget_max' => 1200,
            'currency' => 'PLN',
            'client_name' => 'foto_adam',
            'status' => 'lost',
            'priority' => 'low',
            'notes' => 'Klient wybrał tańszą ofertę',
            'found_at' => now()->subDays(15),
        ]);

        return $listings;
    }

    private function createSpecTemplates(array $listings): array
    {
        $specs = [];

        // Spec dla wygranego listing
        $specs['won'] = SpecTemplate::create([
            'listing_id' => $listings['won']->id,
            'template_id' => null, // Brak powiązania z templates na razie
            'proposed_price' => 2800,
            'proposed_days' => 7,
            'preview_url' => 'https://spec.octadecimal.dev/listing-123456',
            'screenshot_url' => '/storage/specs/listing-123456.png',
            'customizations' => [
                'company_name' => 'Kowalski Budownictwo',
                'primary_color' => '#1E40AF',
                'hero_text' => 'Budujemy Twoje marzenia',
            ],
            'status' => 'won',
            'notes' => 'Klient zachwycony szablonem',
        ]);

        // Spec gotowy dla gabinetu
        $specs['ready'] = SpecTemplate::create([
            'listing_id' => $listings['spec_ready']->id,
            'proposed_price' => 2200,
            'proposed_days' => 5,
            'preview_url' => 'https://spec.octadecimal.dev/listing-234567',
            'customizations' => [
                'company_name' => 'Beauty Spa Kraków',
                'primary_color' => '#EC4899',
            ],
            'status' => 'ready',
            'notes' => 'Elegancki design, pasuje do branży',
        ]);

        return $specs;
    }

    private function createCustomers(array $listings): array
    {
        $customers = [];

        // Klient 1: Aktywny (z wygranego listing)
        $customers['active'] = Customer::create([
            'name' => 'Jan Kowalski',
            'slug' => 'kowalski-budownictwo',
            'code' => 'CLI-001',
            'company_name' => 'Kowalski Budownictwo Sp. z o.o.',
            'nip' => '1234567890',
            'email' => 'jan@kowalski-bud.pl',
            'phone' => '123-456-789',
            'website' => 'https://www.kowalski-bud.pl',
            'address_street' => 'ul. Budowlana 15',
            'address_city' => 'Warszawa',
            'address_postal' => '00-001',
            'source' => 'useme',
            'source_url' => $listings['won']->url,
            'notes' => 'Bardzo zadowolony klient, polecał znajomym',
            'status' => 'active',
            'is_vip' => false,
            'first_order_at' => now()->subDays(8),
            'last_order_at' => now()->subDays(8),
        ]);

        // Klient 2: Prospect (jeszcze nie zaakceptował)
        $customers['prospect'] = Customer::create([
            'name' => 'Anna Nowak',
            'slug' => 'beauty-spa-krakow',
            'code' => 'CLI-002',
            'company_name' => 'Beauty Spa Kraków',
            'email' => 'anna@beautyspa.pl',
            'phone' => '987-654-321',
            'address_city' => 'Kraków',
            'source' => 'useme',
            'source_url' => $listings['spec_ready']->url,
            'status' => 'prospect',
        ]);

        // User dla klienta
        $clientUser = User::create([
            'name' => 'Jan Kowalski',
            'email' => 'jan@kowalski-bud.pl',
            'password' => Hash::make('klient123'),
            'email_verified_at' => now(),
        ]);

        // Pivot customer_user
        $customers['active']->users()->attach($clientUser->id, [
            'role' => 'owner',
            'can_view_billing' => true,
            'can_manage_users' => true,
            'notify_new_invoice' => true,
            'notify_site_updates' => true,
            'invited_at' => now()->subDays(7),
            'accepted_at' => now()->subDays(7),
        ]);

        return $customers;
    }

    private function createSites(array $customers): array
    {
        $sites = [];

        // Strona 1: Live
        $sites['live'] = Site::create([
            'customer_id' => $customers['active']->id,
            'name' => 'Kowalski Budownictwo',
            'slug' => 'kowalski-budownictwo',
            'code' => 'SITE-001',
            'template_slug' => 'business-construction',
            'status' => 'live',
            'staging_url' => 'https://staging.kowalski-budownictwo.octadecimal.dev',
            'production_url' => 'https://www.kowalski-bud.pl',
            'settings' => [
                'primary_color' => '#1E40AF',
                'secondary_color' => '#F59E0B',
                'font_family' => 'Inter',
            ],
            'seo_settings' => [
                'title' => 'Kowalski Budownictwo - Budujemy Twoje marzenia',
                'description' => 'Profesjonalne usługi budowlane w Warszawie i okolicach.',
            ],
            'published_at' => now()->subDays(3),
            'pages_count' => 5,
            'media_count' => 23,
            'last_content_update_at' => now()->subHours(12),
        ]);

        // Strona 2: W development (nowa dla tego samego klienta - rozbudowa)
        $sites['dev'] = Site::create([
            'customer_id' => $customers['active']->id,
            'name' => 'Kowalski Blog',
            'slug' => 'kowalski-blog',
            'code' => 'SITE-002',
            'status' => 'development',
            'staging_url' => 'https://staging.kowalski-blog.octadecimal.dev',
            'settings' => [
                'primary_color' => '#1E40AF',
            ],
            'pages_count' => 2,
            'media_count' => 5,
        ]);

        return $sites;
    }

    private function createOrders(array $customers, array $sites, array $listings, array $specTemplates): array
    {
        $orders = [];

        // Order 1: Główne zlecenie - opłacone
        $orders['paid'] = Order::create([
            'customer_id' => $customers['active']->id,
            'site_id' => $sites['live']->id,
            'listing_id' => $listings['won']->id,
            'spec_template_id' => $specTemplates['won']->id,
            'order_number' => 'ZLC-2026-0001',
            'type' => 'new_site',
            'status' => 'paid',
            'title' => 'Strona www dla firmy budowlanej',
            'scope' => "Zakres zlecenia:\n- Strona główna z hero i CTA\n- Podstrona \"O nas\"\n- Podstrona \"Usługi\" (5 usług)\n- Podstrona \"Realizacje\" z galerią\n- Podstrona \"Kontakt\" z formularzem\n- Responsywność mobile\n- Podstawowe SEO\n- 1 miesiąc darmowych poprawek",
            'price' => 2800,
            'currency' => 'PLN',
            'estimated_days' => 7,
            'deadline_at' => now()->subDays(1),
            'free_corrections_until' => now()->addDays(22), // Zostało ~3 tygodnie
            'useme_offer_url' => 'https://useme.com/pl/jobs/123456/offers/789',
            'offer_sent_at' => now()->subDays(9),
            'accepted_at' => now()->subDays(8),
            'started_at' => now()->subDays(8),
            'delivered_at' => now()->subDays(3),
            'paid_at' => now()->subDays(2),
            'internal_notes' => 'Klient bardzo zadowolony, polecił nas znajomemu',
        ]);

        // Order 2: Zlecenie rozwojowe - w trakcie
        $orders['in_progress'] = Order::create([
            'customer_id' => $customers['active']->id,
            'site_id' => $sites['live']->id,
            'parent_order_id' => $orders['paid']->id,
            'order_number' => 'ZLC-2026-0002',
            'type' => 'development',
            'status' => 'in_progress',
            'title' => 'Dodanie sekcji z referencjami',
            'scope' => "- Sekcja \"Opinie klientów\" na stronie głównej\n- Slider z animacjami\n- Możliwość dodawania opinii z CMS",
            'price' => 500,
            'currency' => 'PLN',
            'estimated_days' => 2,
            'deadline_at' => now()->addDays(3),
            'offer_sent_at' => now()->subDays(2),
            'accepted_at' => now()->subDays(1),
            'started_at' => now()->subDays(1),
            'internal_notes' => 'Klient chce prezentować opinie z Google',
        ]);

        // Order 3: Oferta wysłana (dla prospect)
        $orders['offer_sent'] = Order::create([
            'customer_id' => $customers['prospect']->id,
            'listing_id' => $listings['spec_ready']->id,
            'spec_template_id' => $specTemplates['ready']->id,
            'order_number' => 'ZLC-2026-0003',
            'type' => 'new_site',
            'status' => 'offer_sent',
            'title' => 'Landing page dla gabinetu kosmetycznego',
            'scope' => "- Landing page z hero\n- Sekcja usług\n- Cennik\n- Galeria\n- Formularz kontaktowy\n- Integracja z Google Maps",
            'price' => 2200,
            'currency' => 'PLN',
            'estimated_days' => 5,
            'deadline_at' => now()->addDays(14),
            'offer_sent_at' => now()->subDays(1),
            'useme_offer_url' => 'https://useme.com/pl/jobs/234567/offers/456',
        ]);

        return $orders;
    }

    private function createSiteUsers(array $sites, array $customers): void
    {
        $clientUser = $customers['active']->users->first();

        if ($clientUser) {
            $sites['live']->users()->attach($clientUser->id, [
                'role' => 'editor',
                'can_publish' => false,
                'can_manage_media' => true,
                'can_view_analytics' => true,
                'invited_at' => now()->subDays(7),
                'accepted_at' => now()->subDays(7),
                'last_access_at' => now()->subHours(2),
            ]);
        }
    }

    private function createSiteInfrastructure(array $sites): void
    {
        // Domena produkcyjna
        SiteDomain::create([
            'site_id' => $sites['live']->id,
            'domain' => 'www.kowalski-bud.pl',
            'is_primary' => true,
            'dns_status' => 'active',
            'ssl_status' => 'active',
            'dns_records' => [
                'A' => '51.38.xxx.xxx',
                'CNAME' => 'kowalski-budownictwo.octadecimal.dev',
            ],
            'ssl_expires_at' => now()->addMonths(10),
            'verified_at' => now()->subDays(3),
        ]);

        // Alias bez www
        SiteDomain::create([
            'site_id' => $sites['live']->id,
            'domain' => 'kowalski-bud.pl',
            'is_primary' => false,
            'dns_status' => 'active',
            'ssl_status' => 'active',
            'verified_at' => now()->subDays(3),
        ]);

        // Środowisko staging
        SiteEnvironment::create([
            'site_id' => $sites['live']->id,
            'type' => 'staging',
            'url' => 'https://staging.kowalski-budownictwo.octadecimal.dev',
            'deploy_status' => 'deployed',
            'deployed_at' => now()->subHours(12),
        ]);

        // Środowisko production
        SiteEnvironment::create([
            'site_id' => $sites['live']->id,
            'type' => 'production',
            'url' => 'https://www.kowalski-bud.pl',
            'deploy_status' => 'deployed',
            'deployed_at' => now()->subDays(3),
        ]);

        // Staging dla dev site
        SiteEnvironment::create([
            'site_id' => $sites['dev']->id,
            'type' => 'staging',
            'url' => 'https://staging.kowalski-blog.octadecimal.dev',
            'deploy_status' => 'deployed',
            'deployed_at' => now()->subHours(5),
        ]);
    }

    private function createCorrections(array $orders, array $sites, array $customers): array
    {
        $corrections = [];
        $clientUser = $customers['active']->users->first();

        if (!$clientUser) {
            return $corrections;
        }

        // Poprawka 1: Wykonana i wdrożona
        $corrections['deployed'] = Correction::create([
            'order_id' => $orders['paid']->id,
            'site_id' => $sites['live']->id,
            'reported_by' => $clientUser->id,
            'title' => 'Zmiana numeru telefonu',
            'description' => 'Proszę zmienić numer telefonu na 111-222-333 (stary: 123-456-789)',
            'page_url' => '/kontakt',
            'status' => 'deployed',
            'is_free' => true,
            'reported_at' => now()->subDays(5),
            'accepted_at' => now()->subDays(5),
            'completed_at' => now()->subDays(5),
            'verified_at' => now()->subDays(4),
            'deployed_at' => now()->subDays(4),
            'verified_by' => $clientUser->id,
        ]);

        // Poprawka 2: Zweryfikowana, czeka na deploy
        $corrections['verified'] = Correction::create([
            'order_id' => $orders['paid']->id,
            'site_id' => $sites['live']->id,
            'reported_by' => $clientUser->id,
            'title' => 'Dodanie nowych zdjęć realizacji',
            'description' => 'Proszę dodać 5 nowych zdjęć z ostatniej budowy (wysłane mailem)',
            'page_url' => '/realizacje',
            'status' => 'verified',
            'is_free' => true,
            'reported_at' => now()->subDays(2),
            'accepted_at' => now()->subDays(2),
            'completed_at' => now()->subDays(1),
            'verified_at' => now()->subHours(6),
            'verified_by' => $clientUser->id,
        ]);

        // Poprawka 3: W realizacji
        $corrections['in_progress'] = Correction::create([
            'order_id' => $orders['paid']->id,
            'site_id' => $sites['live']->id,
            'reported_by' => $clientUser->id,
            'title' => 'Poprawka tekstu na stronie O nas',
            'description' => 'W drugim akapicie jest literówka - "budowalnych" zamiast "budowlanych"',
            'page_url' => '/o-nas',
            'status' => 'in_progress',
            'is_free' => true,
            'reported_at' => now()->subHours(3),
            'accepted_at' => now()->subHours(2),
        ]);

        // Poprawka 4: Nowo zgłoszona
        $corrections['reported'] = Correction::create([
            'order_id' => $orders['paid']->id,
            'site_id' => $sites['live']->id,
            'reported_by' => $clientUser->id,
            'title' => 'Aktualizacja cennika usług',
            'description' => 'Proszę zaktualizować ceny w cenniku - nowy cennik w załączniku',
            'page_url' => '/uslugi',
            'status' => 'reported',
            'is_free' => true,
            'reported_at' => now()->subMinutes(30),
        ]);

        return $corrections;
    }

    private function createInvoicesAndPayments(array $customers, array $orders): void
    {
        // Faktura za główne zlecenie
        $invoice = Invoice::create([
            'customer_id' => $customers['active']->id,
            'order_id' => $orders['paid']->id,
            'invoice_number' => 'FV/2026/01/0001',
            'status' => 'paid',
            'type' => 'invoice',
            'subtotal' => 2276.42, // netto
            'discount_amount' => 0,
            'tax_amount' => 523.58, // 23% VAT
            'total' => 2800,
            'currency' => 'PLN',
            'issue_date' => now()->subDays(3),
            'due_date' => now()->subDays(3)->addDays(14),
            'paid_at' => now()->subDays(2),
            'buyer_name' => 'Kowalski Budownictwo Sp. z o.o.',
            'buyer_nip' => '1234567890',
            'buyer_address' => "ul. Budowlana 15\n00-001 Warszawa",
            'pdf_url' => '/storage/invoices/FV-2026-01-0001.pdf',
        ]);

        // Pozycje faktury
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Strona internetowa - projekt i wykonanie',
            'quantity' => 1,
            'unit' => 'szt',
            'unit_price' => 2000,
            'tax_rate' => 23,
            'total' => 2460,
            'order_id' => $orders['paid']->id,
            'sort_order' => 1,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Konfiguracja SEO i optymalizacja',
            'quantity' => 1,
            'unit' => 'szt',
            'unit_price' => 276.42,
            'tax_rate' => 23,
            'total' => 340,
            'sort_order' => 2,
        ]);

        // Płatność
        Payment::create([
            'customer_id' => $customers['active']->id,
            'invoice_id' => $invoice->id,
            'order_id' => $orders['paid']->id,
            'amount' => 2800,
            'currency' => 'PLN',
            'status' => 'completed',
            'payment_method' => 'useme',
            'transaction_id' => 'USEME-TRX-123456',
            'paid_at' => now()->subDays(2),
        ]);
    }

    private function createTimeEntries(User $admin, array $listings, array $orders, array $sites, array $corrections): void
    {
        // Czas na przygotowanie spec template
        TimeEntry::create([
            'user_id' => $admin->id,
            'listing_id' => $listings['won']->id,
            'description' => 'Przygotowanie spec template dla firmy budowlanej',
            'started_at' => now()->subDays(10)->setHour(10),
            'ended_at' => now()->subDays(10)->setHour(10)->addMinutes(45),
            'duration_minutes' => 45,
            'is_billable' => false,
            'category' => 'sales',
        ]);

        // Czas na budowanie strony
        TimeEntry::create([
            'user_id' => $admin->id,
            'order_id' => $orders['paid']->id,
            'site_id' => $sites['live']->id,
            'description' => 'Setup projektu, konfiguracja szablonu',
            'started_at' => now()->subDays(8)->setHour(9),
            'ended_at' => now()->subDays(8)->setHour(10)->addMinutes(30),
            'duration_minutes' => 90,
            'is_billable' => true,
            'hourly_rate' => 150,
            'category' => 'development',
        ]);

        TimeEntry::create([
            'user_id' => $admin->id,
            'order_id' => $orders['paid']->id,
            'site_id' => $sites['live']->id,
            'description' => 'Strona główna i Hero',
            'started_at' => now()->subDays(7)->setHour(9),
            'ended_at' => now()->subDays(7)->setHour(12),
            'duration_minutes' => 180,
            'is_billable' => true,
            'hourly_rate' => 150,
            'category' => 'development',
        ]);

        TimeEntry::create([
            'user_id' => $admin->id,
            'order_id' => $orders['paid']->id,
            'site_id' => $sites['live']->id,
            'description' => 'Podstrony O nas, Usługi, Kontakt',
            'started_at' => now()->subDays(6)->setHour(9),
            'ended_at' => now()->subDays(6)->setHour(14),
            'duration_minutes' => 300,
            'is_billable' => true,
            'hourly_rate' => 150,
            'category' => 'development',
        ]);

        TimeEntry::create([
            'user_id' => $admin->id,
            'order_id' => $orders['paid']->id,
            'site_id' => $sites['live']->id,
            'description' => 'Galeria realizacji, optymalizacja zdjęć',
            'started_at' => now()->subDays(5)->setHour(10),
            'ended_at' => now()->subDays(5)->setHour(12)->addMinutes(30),
            'duration_minutes' => 150,
            'is_billable' => true,
            'hourly_rate' => 150,
            'category' => 'content',
        ]);

        TimeEntry::create([
            'user_id' => $admin->id,
            'order_id' => $orders['paid']->id,
            'site_id' => $sites['live']->id,
            'description' => 'Konfiguracja SEO, deploy na produkcję',
            'started_at' => now()->subDays(4)->setHour(14),
            'ended_at' => now()->subDays(4)->setHour(16),
            'duration_minutes' => 120,
            'is_billable' => true,
            'hourly_rate' => 150,
            'category' => 'development',
        ]);

        // Czas na poprawki (darmowe)
        if (!empty($corrections['deployed'])) {
            TimeEntry::create([
                'user_id' => $admin->id,
                'correction_id' => $corrections['deployed']->id,
                'order_id' => $orders['paid']->id,
                'site_id' => $sites['live']->id,
                'description' => 'Poprawka: zmiana telefonu',
                'started_at' => now()->subDays(5)->setHour(15),
                'ended_at' => now()->subDays(5)->setHour(15)->addMinutes(10),
                'duration_minutes' => 10,
                'is_billable' => false,
                'category' => 'revision',
            ]);
        }

        // Czas na nowe zlecenie rozwojowe
        TimeEntry::create([
            'user_id' => $admin->id,
            'order_id' => $orders['in_progress']->id,
            'site_id' => $sites['live']->id,
            'description' => 'Sekcja referencji - implementacja',
            'started_at' => now()->subHours(4),
            'ended_at' => now()->subHours(2),
            'duration_minutes' => 120,
            'is_billable' => true,
            'hourly_rate' => 150,
            'category' => 'development',
        ]);
    }
}
