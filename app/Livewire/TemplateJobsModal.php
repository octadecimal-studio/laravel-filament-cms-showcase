<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Komponent Livewire do wyświetlania listy jobów analizy szablonów w modalu.
 */
class TemplateJobsModal extends Component
{
    public function render()
    {
        $jobs = DB::table('jobs')
            ->where('queue', 'default')
            ->where('payload', 'like', '%AnalyzeTemplateJob%')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.template-jobs-modal', [
            'jobs' => $jobs,
        ]);
    }

    /**
     * Usuń job z kolejki.
     */
    public function deleteJob(int $jobId): void
    {
        try {
            $job = DB::table('jobs')->where('id', $jobId)->first();
            
            if (! $job) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Job nie znaleziony',
                ]);
                return;
            }

            // Sprawdź czy to AnalyzeTemplateJob
            if (! str_contains($job->payload, 'AnalyzeTemplateJob')) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'To nie jest job analizy szablonu',
                ]);
                return;
            }

            // Usuń job
            DB::table('jobs')->where('id', $jobId)->delete();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Job usunięty pomyślnie',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete template analysis job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Nie udało się usunąć joba',
            ]);
        }
    }
}
