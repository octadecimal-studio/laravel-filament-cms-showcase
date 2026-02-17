<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request dla pobierania listy motocykli.
 */
final class GetMotorcyclesRequest extends FormRequest
{
    /**
     * Czy użytkownik jest autoryzowany.
     */
    public function authorize(): bool
    {
        return true; // Public API
    }

    /**
     * Reguły walidacji.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => 'sometimes|string|max:100',
            'brand' => 'sometimes|string|max:100',
            'available' => 'sometimes|boolean',
            'featured' => 'sometimes|boolean',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'sort' => 'sometimes|string|in:price_asc,price_desc,name_asc,name_desc,newest,engine_asc,engine_desc',
        ];
    }

    /**
     * Przygotowanie danych przed walidacją.
     */
    protected function prepareForValidation(): void
    {
        // Konwersja 'true'/'false' string na boolean
        if ($this->has('available')) {
            $this->merge([
                'available' => filter_var($this->input('available'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        if ($this->has('featured')) {
            $this->merge([
                'featured' => filter_var($this->input('featured'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    /**
     * Niestandardowe komunikaty błędów.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.max' => 'Maximum 100 items per page allowed.',
            'sort.in' => 'Invalid sort option. Allowed: price_asc, price_desc, name_asc, name_desc, newest, engine_asc, engine_desc.',
        ];
    }
}
