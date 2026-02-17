<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use App\Modules\Content\Models\ContentBlock;
use Illuminate\Support\Facades\Validator;

/**
 * Serwis do walidacji danych zgodnie z JSON Schema ContentBlock.
 */
final class ContentBlockValidator
{
    /**
     * Waliduj dane zgodnie z schema ContentBlock.
     *
     * @param  array<string, mixed>  $data  Dane do walidacji
     * @param  ContentBlock  $contentBlock  ContentBlock z schema
     * @return array<string, mixed> Walidowane dane
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $data, ContentBlock $contentBlock): array
    {
        $schema = $contentBlock->schema ?? [];
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        $rules = [];
        $messages = [];

        foreach ($properties as $fieldName => $fieldSchema) {
            $fieldRules = $this->buildValidationRules($fieldName, $fieldSchema, $required);
            $rules["data.{$fieldName}"] = $fieldRules;
        }

        $validator = Validator::make($data, $rules, $messages);

        return $validator->validate();
    }

    /**
     * Zbuduj reguły walidacji dla pola.
     *
     * @param  string  $fieldName  Nazwa pola
     * @param  array<string, mixed>  $fieldSchema  Schema pola
     * @param  array<string>  $required  Lista wymaganych pól
     * @return array<string>
     */
    private function buildValidationRules(string $fieldName, array $fieldSchema, array $required): array
    {
        $rules = [];
        $type = $fieldSchema['type'] ?? 'string';

        // Wymagane
        if (in_array($fieldName, $required)) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Typ
        match ($type) {
            'string', 'text', 'textarea' => $rules[] = 'string',
            'number', 'integer' => $rules[] = 'numeric',
            'boolean' => $rules[] = 'boolean',
            'array' => $rules[] = 'array',
            'object' => $rules[] = 'array',
            default => $rules[] = 'string',
        };

        // Długość (dla string)
        if ($type === 'string' || $type === 'text') {
            if (isset($fieldSchema['minLength'])) {
                $rules[] = "min:{$fieldSchema['minLength']}";
            }
            if (isset($fieldSchema['maxLength'])) {
                $rules[] = "max:{$fieldSchema['maxLength']}";
            }
        }

        // Min/Max (dla number)
        if ($type === 'number' || $type === 'integer') {
            if (isset($fieldSchema['minimum'])) {
                $rules[] = "min:{$fieldSchema['minimum']}";
            }
            if (isset($fieldSchema['maximum'])) {
                $rules[] = "max:{$fieldSchema['maximum']}";
            }
        }

        // Format (email, url)
        if (isset($fieldSchema['format'])) {
            match ($fieldSchema['format']) {
                'email' => $rules[] = 'email',
                'uri', 'url' => $rules[] = 'url',
                default => null,
            };
        }

        // Pattern (regex)
        if (isset($fieldSchema['pattern'])) {
            $rules[] = "regex:{$fieldSchema['pattern']}";
        }

        // Enum
        if (isset($fieldSchema['enum'])) {
            $rules[] = 'in:'.implode(',', $fieldSchema['enum']);
        }

        return $rules;
    }
}
