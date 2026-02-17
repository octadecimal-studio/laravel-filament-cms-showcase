<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request dla pobierania kontentu.
 */
final class GetContentRequest extends FormRequest
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
            'env' => 'sometimes|string|in:staging,production',
        ];
    }

    /**
     * Domyślne wartości.
     *
     * @return array<string, mixed>
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'env' => $this->input('env', 'production'),
        ]);
    }
}
