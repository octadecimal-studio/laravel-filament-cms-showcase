<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widgets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller do zarządzania jobami analizy szablonów z widgetu.
 */
class TemplateJobsController extends Controller
{
    /**
     * Usuń job z kolejki.
     */
    public function delete(Request $request, int $jobId)
    {
        try {
            $job = DB::table('jobs')->where('id', $jobId)->first();
            
            if (! $job) {
                return response()->json(['error' => 'Job nie znaleziony'], 404);
            }

            // Sprawdź czy to AnalyzeTemplateJob
            if (! str_contains($job->payload, 'AnalyzeTemplateJob')) {
                return response()->json(['error' => 'To nie jest job analizy szablonu'], 400);
            }

            // Usuń job
            DB::table('jobs')->where('id', $jobId)->delete();

            Log::info('Template analysis job deleted', [
                'job_id' => $jobId,
                'user_id' => auth()->id() ?? null,
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to delete template analysis job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Nie udało się usunąć joba'], 500);
        }
    }
}
