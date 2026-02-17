<?php

declare(strict_types=1);

namespace App\Plugins\Reservations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request walidacji dla tworzenia rezerwacji.
 *
 * Waliduje dane z formularza rezerwacji z frontendu.
 */
class StoreReservationRequest extends FormRequest
{
    /**
     * Czy użytkownik ma prawo wykonać request.
     *
     * Formularz rezerwacji jest publiczny.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reguły walidacji.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Motocykl (opcjonalny - ID lub nazwa z zewnętrznego systemu)
            'motorcycle_id' => [
                'nullable',
                'string',
                'max:255',
            ],

            // Dane klienta
            'customer_name' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],

            'customer_email' => [
                'required',
                'email:rfc,dns',
                'max:255',
            ],

            'customer_phone' => [
                'required',
                'string',
                'min:9',
                'max:20',
                // Opcjonalnie: regex dla polskich numerów
                // 'regex:/^(\+48)?[0-9]{9}$/',
            ],

            // Daty rezerwacji
            'pickup_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],

            'return_date' => [
                'required',
                'date',
                'after:pickup_date',
            ],

            // Notatki
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            // RODO (wymagane)
            'rodo_consent' => [
                'required',
                'accepted',
            ],
        ];
    }

    /**
     * Niestandardowe komunikaty błędów.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Imię i nazwisko jest wymagane.',
            'customer_name.min' => 'Imię i nazwisko musi mieć co najmniej 2 znaki.',

            'customer_email.required' => 'Adres email jest wymagany.',
            'customer_email.email' => 'Podaj prawidłowy adres email.',

            'customer_phone.required' => 'Numer telefonu jest wymagany.',
            'customer_phone.min' => 'Numer telefonu musi mieć co najmniej 9 cyfr.',

            'pickup_date.required' => 'Data odbioru jest wymagana.',
            'pickup_date.after_or_equal' => 'Data odbioru nie może być w przeszłości.',

            'return_date.required' => 'Data zwrotu jest wymagana.',
            'return_date.after' => 'Data zwrotu musi być po dacie odbioru.',

            'rodo_consent.required' => 'Zgoda na przetwarzanie danych jest wymagana.',
            'rodo_consent.accepted' => 'Musisz wyrazić zgodę na przetwarzanie danych osobowych.',

            'motorcycle_id.exists' => 'Wybrany motocykl nie istnieje.',
        ];
    }

    /**
     * Nazwy atrybutów dla komunikatów.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'imię i nazwisko',
            'customer_email' => 'email',
            'customer_phone' => 'telefon',
            'pickup_date' => 'data odbioru',
            'return_date' => 'data zwrotu',
            'motorcycle_id' => 'motocykl',
            'rodo_consent' => 'zgoda RODO',
        ];
    }
}
