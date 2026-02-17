<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Widget wyświetlający listę jobów analizy szablonów.
 */
class TemplateAnalysisJobsWidget extends Widget
{
    protected static string $view = 'filament.widgets.template-jobs-list';

    protected static ?int $sort = 2;

    /**
     * Tylko super admin widzi ten widget.
     */
    public static function canView(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user instanceof User && ($user->is_super_admin || $user->hasRole('super_admin'));
    }

    /**
     * Przekaż dane do widoku.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $jobs = DB::table('jobs')
            ->where('queue', 'default')
            ->where('payload', 'like', '%AnalyzeTemplateJob%')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'jobs' => $jobs,
        ];
    }
}
