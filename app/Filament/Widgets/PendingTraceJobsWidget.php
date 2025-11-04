<?php

namespace App\Filament\Widgets;

use App\Models\TraceJob;
use App\Services\FunctionHelp;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingTraceJobsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    use HasWidgetShield;

    protected function getStats(): array
    {
        $pendingJobs = TraceJob::where('status', 'pending')->count();
        $processingJobs = TraceJob::where('status', 'processing')->count();
        $completedJobs = TraceJob::where('status', 'completed')->count();
        $failedJobs = TraceJob::where('status', 'failed')->count();

        return [
            Stat::make('Job đang chờ', $pendingJobs)
                ->description('Chưa được xử lý')
                ->descriptionIcon('heroicon-o-clock')
                ->descriptionColor('warning')
                ->color('warning'),

            Stat::make('Job đang xử lý', $processingJobs)
                ->description('Đang chạy')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->descriptionColor('info')
                ->color('info'),

            Stat::make('Job hoàn thành', $completedJobs)
                ->description('Thành công')
                ->descriptionIcon('heroicon-o-check-circle')
                ->descriptionColor('success')
                ->color('success'),

            Stat::make('Job thất bại', $failedJobs)
                ->description('Cần xử lý lại')
                ->descriptionIcon('heroicon-o-x-circle')
                ->descriptionColor('danger')
                ->color('danger'),
        ];
    }
}

