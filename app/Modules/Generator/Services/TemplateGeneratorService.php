<?php

declare(strict_types=1);

namespace App\Modules\Generator\Services;

use App\Modules\Generator\Models\GeneratedTemplate;
use App\Modules\Generator\Models\PromptTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serwis do generowania szablonów przez AI (Claude/OpenAI).
 */
final class TemplateGeneratorService
{
    /**
     * Generuj szablon używając Claude API.
     *
     * @param  string  $prompt  Prompt użytkownika
     * @param  string|null  $imageUrl  URL obrazka (Vision API)
     * @param  PromptTemplate|null  $promptTemplate  Prompt template (opcjonalny)
     * @param  string  $model  Model AI (claude-sonnet-4, claude-opus-3, etc.)
     */
    public function generateWithClaude(
        string $prompt,
        ?string $imageUrl = null,
        ?PromptTemplate $promptTemplate = null,
        string $model = 'claude-sonnet-4-20250514'
    ): GeneratedTemplate {
        $generated = GeneratedTemplate::create([
            'prompt' => $prompt,
            'image_url' => $imageUrl,
            'model' => $model,
            'status' => 'generating',
            'prompt_template_id' => $promptTemplate?->id,
        ]);

        try {
            // Renderuj prompt z template jeśli dostępny
            $finalPrompt = $promptTemplate?->render(['prompt' => $prompt]) ?? $prompt;

            // Buduj system prompt
            $systemPrompt = $this->buildSystemPrompt();

            // Przygotuj messages
            $messages = [
                [
                    'role' => 'user',
                    'content' => $finalPrompt,
                ],
            ];

            // Jeśli jest obrazek, dodaj do content
            if ($imageUrl) {
                $imageData = $this->fetchImageBase64($imageUrl);
                if ($imageData) {
                    $messages[0]['content'] = [
                        [
                            'type' => 'text',
                            'text' => $finalPrompt,
                        ],
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => 'image/jpeg',
                                'data' => $imageData,
                            ],
                        ],
                    ];
                }
            }

            // Wywołaj Claude API z wydłużonym timeoutem (generowanie może trwać 1-3 minuty)
            $response = Http::timeout(180) // 3 minuty dla generowania szablonów
                ->withHeaders([
                    'x-api-key' => config('services.anthropic.api_key', env('ANTHROPIC_API_KEY')),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 4096,
                    'system' => $systemPrompt,
                    'messages' => $messages,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Claude API error: '.$response->body());
            }

            $responseData = $response->json();
            $content = $responseData['content'][0]['text'] ?? '';

            // Parsuj odpowiedź (JSON z komponentami)
            $generatedCode = $this->parseGeneratedCode($content);

            // Oblicz metadane
            $metadata = [
                'tokens_input' => $responseData['usage']['input_tokens'] ?? 0,
                'tokens_output' => $responseData['usage']['output_tokens'] ?? 0,
                'tokens_total' => $responseData['usage']['input_tokens'] + ($responseData['usage']['output_tokens'] ?? 0),
                'model' => $model,
                'time' => now()->toIso8601String(),
            ];

            // Oznacz jako zakończone
            $generated->markAsCompleted($generatedCode, $metadata);

            // Zaktualizuj statystyki prompt template
            if ($promptTemplate) {
                $promptTemplate->incrementUsage();
            }

            return $generated;
        } catch (\Exception $e) {
            Log::error('Template generation failed', [
                'error' => $e->getMessage(),
                'generated_id' => $generated->id,
            ]);

            $generated->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Generuj szablon używając OpenAI API.
     *
     * @param  string  $prompt  Prompt użytkownika
     * @param  string|null  $imageUrl  URL obrazka (Vision API)
     * @param  PromptTemplate|null  $promptTemplate  Prompt template (opcjonalny)
     * @param  string  $model  Model AI (gpt-4, gpt-4-turbo, etc.)
     */
    public function generateWithOpenAI(
        string $prompt,
        ?string $imageUrl = null,
        ?PromptTemplate $promptTemplate = null,
        string $model = 'gpt-4-turbo-preview'
    ): GeneratedTemplate {
        $generated = GeneratedTemplate::create([
            'prompt' => $prompt,
            'image_url' => $imageUrl,
            'model' => $model,
            'status' => 'generating',
            'prompt_template_id' => $promptTemplate?->id,
        ]);

        try {
            // Renderuj prompt z template jeśli dostępny
            $finalPrompt = $promptTemplate?->render(['prompt' => $prompt]) ?? $prompt;

            // Buduj system prompt
            $systemPrompt = $this->buildSystemPrompt();

            // Przygotuj messages
            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $imageUrl
                        ? [
                            [
                                'type' => 'text',
                                'text' => $finalPrompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $imageUrl,
                                ],
                            ],
                        ]
                        : $finalPrompt,
                ],
            ];

            // Wywołaj OpenAI API z wydłużonym timeoutem (generowanie może trwać 1-3 minuty)
            $response = Http::timeout(180) // 3 minuty dla generowania szablonów
                ->withHeaders([
                    'Authorization' => 'Bearer '.config('services.openai.api_key', env('OPENAI_API_KEY')),
                    'Content-Type' => 'application/json',
                ])->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => 4096,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('OpenAI API error: '.$response->body());
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'] ?? '';

            // Parsuj odpowiedź (JSON z komponentami)
            $generatedCode = $this->parseGeneratedCode($content);

            // Oblicz metadane
            $metadata = [
                'tokens_input' => $responseData['usage']['prompt_tokens'] ?? 0,
                'tokens_output' => $responseData['usage']['completion_tokens'] ?? 0,
                'tokens_total' => $responseData['usage']['total_tokens'] ?? 0,
                'model' => $model,
                'time' => now()->toIso8601String(),
            ];

            // Oznacz jako zakończone
            $generated->markAsCompleted($generatedCode, $metadata);

            // Zaktualizuj statystyki prompt template
            if ($promptTemplate) {
                $promptTemplate->incrementUsage();
            }

            return $generated;
        } catch (\Exception $e) {
            Log::error('Template generation failed', [
                'error' => $e->getMessage(),
                'generated_id' => $generated->id,
            ]);

            $generated->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Buduj system prompt dla AI.
     */
    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Jesteś ekspertem w tworzeniu nowoczesnych szablonów Next.js z TypeScript i Tailwind CSS.

Twoim zadaniem jest generowanie kompletnych komponentów React/Next.js w formacie JSON z następującą strukturą:

{
  "components": [
    {
      "name": "Hero",
      "type": "tsx",
      "code": "// kod komponentu TSX",
      "dependencies": ["framer-motion"],
      "styles": "// dodatkowe style jeśli potrzebne"
    }
  ],
  "metadata": {
    "description": "Opis szablonu",
    "category": "hero|features|gallery|contact|full-page",
    "tech_stack": ["next.js", "typescript", "tailwindcss"]
  }
}

Wymagania:
- Używaj TypeScript strict mode
- Używaj Tailwind CSS dla stylów
- Komponenty powinny być responsive
- Dodaj komentarze w kodzie
- Używaj najlepszych praktyk React/Next.js
- Zwracaj tylko poprawny JSON bez dodatkowych znaków
PROMPT;
    }

    /**
     * Parsuj wygenerowany kod z odpowiedzi AI.
     *
     * @param  string  $content  Odpowiedź z AI
     * @return array<string, mixed>
     */
    private function parseGeneratedCode(string $content): array
    {
        // Usuń markdown code blocks jeśli są
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        // Parsuj JSON
        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Jeśli nie jest JSON, zwróć jako surowy kod
            return [
                'raw' => $content,
                'components' => [],
                'metadata' => [],
            ];
        }

        return $parsed;
    }

    /**
     * Pobierz obrazek jako base64.
     *
     * @param  string  $imageUrl  URL obrazka
     * @return string|null Base64 encoded image
     */
    private function fetchImageBase64(string $imageUrl): ?string
    {
        try {
            $imageData = Http::timeout(30)->get($imageUrl)->body();
            $base64 = base64_encode($imageData);

            return $base64;
        } catch (\Exception $e) {
            Log::warning('Failed to fetch image for Vision API', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
