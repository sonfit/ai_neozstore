<?php

namespace App\Filament\Widgets;

use App\Models\ThuTin;
use App\Models\TaskList;
use App\Models\TongHopTinhHinh;
use App\Models\User;
use App\Models\TraceJob;
use App\Services\FunctionHelp;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Illuminate\Support\Facades\DB;

class StatsOverviewWidget extends BaseWidget
{
    use HasWidgetShield;
    protected static ?int $sort = 1;


    protected function getStats(): array
    {
        $user = auth()->user();
        $isAdmin = FunctionHelp::isAdminUser();

        $stats = [];

        // Chỉ admin mới hiển thị tổng hợp tình hình
        if ($isAdmin) {
            $totalTongHopTinhhinh = TongHopTinhHinh::count();
            $todayTongHopTinhhinh = TongHopTinhhinh::whereDate('created_at', today())->count();
            $yesterdayTongHopTinhhinh = TongHopTinhhinh::whereDate('created_at', today()->subDay())->count();

            $stats[] = Stat::make('Tổng số tổng hợp', Number::format($totalTongHopTinhhinh))
                ->description($totalTongHopTinhhinh . ' tổng hợp hôm nay')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->descriptionColor($todayTongHopTinhhinh > $yesterdayTongHopTinhhinh ? 'success' : 'warning')
                ->color('info')
                ->chart($this->getTongHopTinhHinhChartData());
        }

        // Thu thập tin - filter theo role
        $thuTinQuery = ThuTin::query();
        if (!$isAdmin && FunctionHelp::isUser()) {
            $mucTieuIds = $user->mucTieus()->pluck('muc_tieus.id')->toArray();
            if (!empty($mucTieuIds)) {
                $thuTinQuery->whereIn('id_muctieu', $mucTieuIds);
            } else {
                $thuTinQuery->whereRaw('1 = 0');
            }
        }

        $totalThuTin = $thuTinQuery->count();
        $todayThuTin = (clone $thuTinQuery)->whereDate('created_at', today())->count();
        $yesterdayThuTin = (clone $thuTinQuery)->whereDate('created_at', today()->subDay())->count();

        $stats[] = Stat::make('Tổng số thu thập tin', Number::format($totalThuTin))
            ->description($todayThuTin . ' tin hôm nay')
            ->descriptionIcon('heroicon-o-arrow-trending-up')
            ->descriptionColor($todayThuTin > $yesterdayThuTin ? 'success' : 'warning')
            ->color('success')
            ->chart($this->getThuTinChartData());

        // Công việc (TaskList) - admin xem tất cả, user xem của mình
        $taskListQuery = TaskList::query();
        if (!$isAdmin && $user) {
            $taskListQuery->where('user_id', $user->id);
        }

        $totalTaskLists = (clone $taskListQuery)->count();
        $todayTaskLists = (clone $taskListQuery)->whereDate('created_at', today())->count();
        $yesterdayTaskLists = (clone $taskListQuery)->whereDate('created_at', today()->subDay())->count();

        $pivot = DB::table('task_list_thu_tin');
        if (!$isAdmin && $user) {
            $pivot->join('task_lists', 'task_lists.id', '=', 'task_list_thu_tin.task_list_id')
                ->where('task_lists.user_id', $user->id);
        }
        $totalAttachedThuTin = (clone $pivot)->distinct()->count('task_list_thu_tin.thu_tin_id');

        $stats[] = Stat::make('Tổng số công việc', Number::format($totalTaskLists))
            ->description($todayTaskLists . ' công việc hôm nay')
            ->descriptionIcon('heroicon-o-clipboard-document-list')
            ->descriptionColor($todayTaskLists > $yesterdayTaskLists ? 'success' : 'warning')
            ->color('info')
            ->chart($this->getTaskListChartData($isAdmin));

        $stats[] = Stat::make('Tổng số tin trong công việc', Number::format($totalAttachedThuTin))
            ->description('ThuTin được gắn với công việc')
            ->descriptionIcon('heroicon-o-document-text')
            ->color('success');

        // Chỉ admin mới hiển thị số người dùng
        if ($isAdmin) {
            $totalUsers = User::count();
            $stats[] = Stat::make('Tổng số người dùng', Number::format($totalUsers))
                ->description('Người dùng hệ thống')
                ->descriptionIcon('heroicon-o-users')
                ->color('warning');
        }

        // Chỉ admin mới hiển thị job chờ
        if ($isAdmin) {
            $pendingJobs = TraceJob::where('status', 'pending')->count();
            $stats[] = Stat::make('Job đang chờ', Number::format($pendingJobs))
                ->description('Chưa được xử lý')
                ->descriptionIcon('heroicon-o-clock')
                ->descriptionColor($pendingJobs > 0 ? 'warning' : 'success')
                ->color($pendingJobs > 0 ? 'danger' : 'success');
        }

        return $stats;
    }

    protected function getTongHopTinhHinhChartData(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = TongHopTinhHinh::whereDate('created_at', today()->subDays($i))->count();
        }
        return $data;
    }

    protected function getThuTinChartData(): array
    {
        $data = [];
        $user = auth()->user();
        $isAdmin = FunctionHelp::isAdminUser();

        for ($i = 6; $i >= 0; $i--) {
            $thuTinQuery = ThuTin::whereDate('created_at', today()->subDays($i));

            if (!$isAdmin && FunctionHelp::isUser()) {
                $mucTieuIds = $user->mucTieus()->pluck('muc_tieus.id')->toArray();
                if (!empty($mucTieuIds)) {
                    $thuTinQuery->whereIn('id_muctieu', $mucTieuIds);
                } else {
                    $thuTinQuery->whereRaw('1 = 0');
                }
            }

            $data[] = $thuTinQuery->count();
        }
        return $data;
    }

    protected function getTaskListChartData(bool $isAdmin): array
    {
        $data = [];
        $user = auth()->user();
        for ($i = 6; $i >= 0; $i--) {
            $q = TaskList::whereDate('created_at', today()->subDays($i));
            if (!$isAdmin && $user) {
                $q->where('user_id', $user->id);
            }
            $data[] = $q->count();
        }
        return $data;
    }
}
