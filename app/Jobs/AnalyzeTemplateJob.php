<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Generator\Models\Template;
use App\Modules\Generator\Services\TemplateAnalyzerService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job do analizy szablonu przez AI.
 *
 * Analizuje strukturę szablonu Next.js i generuje ContentBlocks.
 */
class AnalyzeTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Liczba prób wykonania joba.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * Timeout w sekundach (3 minuty dla analizy struktury).
     *
     * @var int
     */
    public $timeout = 180;

    /**
     * Utwórz nowy job.
     */
    public function __construct(
        public string $templateId,
        public ?string $userId = null
    ) {
    }

    /**
     * Wykonaj job.
     */
    public function handle(TemplateAnalyzerService $analyzer): void
    {
        $template = Template::findOrFail($this->templateId);

        if (! $template->directory_path) {
            Log::warning('Template has no directory_path, skipping analysis', [
                'template_id' => $template->id,
            ]);

            return;
        }

        try {
            // Ustaw status na analyzing
            $template->update([
                'analysis_status' => 'analyzing',
                'analysis_progress' => 0,
            ]);

            Log::info('Starting template analysis', [
                'template_id' => $template->id,
                'template_path' => $template->directory_path,
            ]);

            // Analizuj całą strukturę szablonu (z progress callback)
            $analysis = $analyzer->analyzeTemplateStructure(
                $template->directory_path,
                $template->name ?? 'template',
                function (int $progress) use ($template) {
                    // Aktualizuj progress (0-70% dla analizy)
                    $template->update(['analysis_progress' => (int) ($progress * 0.7)]);
                }
            );

            // Wygeneruj ContentBlocks (70-100%)
            $template->update(['analysis_progress' => 70]);
            $blocks = $analyzer->generateContentBlocks($analysis);
            $template->update(['analysis_progress' => 100]);

            // Ustaw status na completed
            $template->update([
                'analysis_status' => 'completed',
                'analysis_progress' => 100,
            ]);

            Log::info('Template analysis completed', [
                'template_id' => $template->id,
                'blocks_count' => count($blocks),
            ]);

            // Wygeneruj miniaturkę (w tle)
            dispatch(function () use ($template) {
                try {
                    $thumbnailService = app(\App\Modules\Generator\Services\ThumbnailGeneratorService::class);
                    $thumbnailService->generateThumbnail($template);
                } catch (\Exception $e) {
                    Log::warning('Thumbnail generation failed', [
                        'template_id' => $template->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();

            // Wyślij powiadomienie do użytkownika (jeśli podano)
            if ($this->userId) {
                Notification::make()
                    ->success()
                    ->title('Analiza AI zakończona')
                    ->body('Utworzono '.count($blocks).' ContentBlocks z analizy struktury szablonu.')
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }
        } catch (\Exception $e) {
            // Ustaw status na failed
            $template->update([
                'analysis_status' => 'failed',
                'analysis_progress' => 0,
            ]);

            Log::error('Template analysis failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Wyślij powiadomienie o błędzie
            if ($this->userId) {
                $errorMessage = $e->getMessage();
                if (str_contains($errorMessage, 'timed out') || str_contains($errorMessage, 'timeout')) {
                    $errorMessage = 'Analiza AI przekroczyła limit czasu. Spróbuj ponownie później lub zmniejsz rozmiar szablonu.';
                } elseif (str_contains($errorMessage, 'Claude API error')) {
                    $errorMessage = 'Błąd połączenia z API Claude. Sprawdź klucz API i połączenie internetowe.';
                }

                Notification::make()
                    ->danger()
                    ->title('Błąd analizy AI')
                    ->body($errorMessage)
                    ->persistent()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

            throw $e; // Rethrow aby job mógł być ponowiony
        }
    }

    /**
     * Obsługa niepowodzenia joba.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeTemplateJob failed permanently', [
            'template_id' => $this->templateId,
            'error' => $exception->getMessage(),
        ]);

        // Wyślij powiadomienie o ostatecznym niepowodzeniu
        if ($this->userId) {
            Notification::make()
                ->danger()
                ->title('Analiza AI nie powiodła się')
                ->body('Analiza szablonu zakończyła się niepowodzeniem po wszystkich próbach. Sprawdź logi dla szczegółów.')
                ->persistent()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }
}
