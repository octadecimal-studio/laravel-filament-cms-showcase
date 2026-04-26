<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\KeyValue;
use App\Modules\Content\Models\ContentBlock;
use Filament\Forms;
use Illuminate\Support\Collection;

/**
 * Serwis do budowania dynamicznych formularzy z JSON Schema ContentBlock.
 */
final class ContentBlockFormBuilder
{
    /**
     * Zbuduj formularz z schema ContentBlock.
     *
     * @param  ContentBlock|null  $contentBlock  ContentBlock (opcjonalnie)
     * @param  array<string, mixed>|null  $currentData  Aktualne dane (dla edycji)
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public function buildForm(?ContentBlock $contentBlock, ?array $currentData = null): array
    {
        if (! $contentBlock || ! $contentBlock->schema) {
            return [
                Placeholder::make('no_block')
                    ->label('')
                    ->content('Wybierz ContentBlock aby zobaczyć formularz')
                    ->columnSpanFull(),
            ];
        }

        $schema = $contentBlock->schema;
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        $components = [];

        foreach ($properties as $fieldName => $fieldSchema) {
            $component = $this->buildField($fieldName, $fieldSchema, $required, $currentData);
            if ($component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * Zbuduj pojedyncze pole z JSON Schema.
     *
     * @param  string  $fieldName  Nazwa pola
     * @param  array<string, mixed>  $fieldSchema  Schema pola
     * @param  array<string>  $required  Lista wymaganych pól
     * @param  array<string, mixed>|null  $currentData  Aktualne dane
     * @return \Filament\Schemas\Components\Component|null
     */
    private function buildField(
        string $fieldName,
        array $fieldSchema,
        array $required,
        ?array $currentData = null
    ): ?Component {
        $type = $fieldSchema['type'] ?? 'string';
        $title = $fieldSchema['title'] ?? $this->humanizeFieldName($fieldName);
        $description = $fieldSchema['description'] ?? null;
        $isRequired = in_array($fieldName, $required);
        $defaultValue = $currentData[$fieldName] ?? $fieldSchema['default'] ?? null;

        return match ($type) {
            'string' => $this->buildStringField($fieldName, $title, $description, $isRequired, $defaultValue, $fieldSchema),
            'text', 'textarea' => $this->buildTextareaField($fieldName, $title, $description, $isRequired, $defaultValue),
            'number', 'integer' => $this->buildNumberField($fieldName, $title, $description, $isRequired, $defaultValue, $fieldSchema),
            'boolean' => $this->buildBooleanField($fieldName, $title, $description, $isRequired, $defaultValue),
            'array' => $this->buildArrayField($fieldName, $title, $description, $isRequired, $defaultValue, $fieldSchema),
            'object' => $this->buildObjectField($fieldName, $title, $description, $isRequired, $defaultValue, $fieldSchema),
            default => $this->buildStringField($fieldName, $title, $description, $isRequired, $defaultValue, $fieldSchema),
        };
    }

    /**
     * Zbuduj pole tekstowe.
     *
     * @return TextInput|Select
     */
    private function buildStringField(
        string $name,
        string $label,
        ?string $description,
        bool $required,
        mixed $default,
        array $schema
    ): Component {
        $field = TextInput::make("data.{$name}")
            ->label($label)
            ->default($default);

        if ($description) {
            $field->helperText($description);
        }

        if ($required) {
            $field->required();
        }

        // Max length
        if (isset($schema['maxLength'])) {
            $field->maxLength($schema['maxLength']);
        }

        // Min length
        if (isset($schema['minLength'])) {
            $field->minLength($schema['minLength']);
        }

        // Pattern (regex)
        if (isset($schema['pattern'])) {
            $field->regex($schema['pattern']);
        }

        // Enum (select)
        if (isset($schema['enum'])) {
            return Select::make("data.{$name}")
                ->label($label)
                ->options(array_combine($schema['enum'], $schema['enum']))
                ->default($default)
                ->required($required)
                ->helperText($description)
                ->native(false);
        }

        // Format (email, url, etc.)
        if (isset($schema['format'])) {
            match ($schema['format']) {
                'email' => $field->email(),
                'uri', 'url' => $field->url(),
                default => null,
            };
        }

        return $field;
    }

    /**
     * Zbuduj pole textarea.
     */
    private function buildTextareaField(
        string $name,
        string $label,
        ?string $description,
        bool $required,
        mixed $default
    ): Textarea {
        $field = Textarea::make("data.{$name}")
            ->label($label)
            ->rows(3)
            ->default($default);

        if ($description) {
            $field->helperText($description);
        }

        if ($required) {
            $field->required();
        }

        return $field;
    }

    /**
     * Zbuduj pole numeryczne.
     */
    private function buildNumberField(
        string $name,
        string $label,
        ?string $description,
        bool $required,
        mixed $default,
        array $schema
    ): TextInput {
        $field = TextInput::make("data.{$name}")
            ->label($label)
            ->numeric()
            ->default($default);

        if ($description) {
            $field->helperText($description);
        }

        if ($required) {
            $field->required();
        }

        // Min/Max
        if (isset($schema['minimum'])) {
            $field->minValue($schema['minimum']);
        }

        if (isset($schema['maximum'])) {
            $field->maxValue($schema['maximum']);
        }

        return $field;
    }

    /**
     * Zbuduj pole boolean (toggle).
     */
    private function buildBooleanField(
        string $name,
        string $label,
        ?string $description,
        bool $required,
        mixed $default
    ): Toggle {
        $field = Toggle::make("data.{$name}")
            ->label($label)
            ->default($default ?? false);

        if ($description) {
            $field->helperText($description);
        }

        return $field;
    }

    /**
     * Zbuduj pole array (repeater lub tags).
     */
    private function buildArrayField(
        string $name,
        string $label,
        ?string $description,
        bool $required,
        mixed $default,
        array $schema
    ): Component {
        $items = $schema['items'] ?? [];

        // Jeśli items to string (array of strings) - użyj TagsInput
        if (isset($items['type']) && $items['type'] === 'string') {
            $field = TagsInput::make("data.{$name}")
                ->label($label)
                ->separator(',')
                ->default($default ?? []);

            if ($description) {
                $field->helperText($description);
            }

            return $field;
        }

        // W przeciwnym razie użyj Repeater
        $field = Repeater::make("data.{$name}")
            ->label($label)
            ->default($default ?? [])
            ->schema([
                KeyValue::make('item')
                    ->keyLabel('Klucz')
                    ->valueLabel('Wartość'),
            ]);

        if ($description) {
            $field->helperText($description);
        }

        if ($required) {
            $field->required();
        }

        return $field;
    }

    /**
     * Zbuduj pole object (KeyValue).
     */
    private function buildObjectField(
        string $name,
        string $label,
        ?string $description,
        bool $required,
        mixed $default,
        array $schema
    ): KeyValue {
        $field = KeyValue::make("data.{$name}")
            ->label($label)
            ->keyLabel('Klucz')
            ->valueLabel('Wartość')
            ->default($default ?? []);

        if ($description) {
            $field->helperText($description);
        }

        return $field;
    }

    /**
     * Przekształć nazwę pola na czytelną formę.
     */
    private function humanizeFieldName(string $name): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $name));
    }
}
