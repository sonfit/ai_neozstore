<?php

namespace App\Filament\Widgets;

use App\Models\ThuTin;
use App\Services\FunctionHelp;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Str;

class LatestThuTinWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    use HasWidgetShield;

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();

                // Nếu user có quyền admin hoặc super_admin thì xem tất cả
                if (FunctionHelp::isAdminUser()) {
                    return $query;
                }

                // Nếu user có quyền user thì chỉ xem tin thuộc mục tiêu mà user theo dõi
                if (FunctionHelp::isUser()) {
                    $mucTieuIds = $user->mucTieus()->pluck('muc_tieus.id')->toArray();

                    // Nếu user chưa theo dõi mục tiêu nào thì không hiển thị gì
                    if (empty($mucTieuIds)) {
                        return $query->whereRaw('1 = 0'); // Always false condition
                    }

                    return $query->whereIn('id_muctieu', $mucTieuIds);
                }

                return $query;
            })
            ->query(
                ThuTin::query()
                    ->with(['mucTieu', 'bot'])
                    ->latest('created_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('bot.ten_bot')
                    ->label('Tên Bot')
                    ->description(fn($record) => $record->bot->loai_bot ?? null),

                Tables\Columns\TextColumn::make('link')
                    ->label('Link')
                    ->url(fn($record) => $record->link, true)
                    ->limit(50)
                    ->description(fn($record) => $record->contents_text ? Str::limit($record->contents_text, 50) : '')
                    ->tooltip(fn($record) => $record->contents_text ?? '')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('mucTieu.name')
                    ->label('Mục tiêu')
                    ->badge()
                    ->limit(15)
                    ->tooltip(fn($record) => $record->mucTieu->name ?? '')
                    ->color('info'),

                Tables\Columns\TextColumn::make('phanloai')
                    ->label('Phân loại')
                    ->badge()
                    ->formatStateUsing(
                        fn ($state) => trans("options.phanloai.$state") !== "options.phanloai.$state"
                            ? trans("options.phanloai.$state")
                            : 'Chưa xác định'
                    )
                    ->color(fn($state) => match($state) {
                        1, 2 => 'danger',
                        3, 4 => 'warning',
                        5 => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('diem')
                    ->label('Mức độ')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        $level = FunctionHelp::diemToLevel($state);
                        return "Level {$level} ({$state} điểm)";
                    })
                    ->color(function ($state) {
                        $level = FunctionHelp::diemToLevel($state);
                        return FunctionHelp::levelBadgeColor($level);
                    }),

                Tables\Columns\TextColumn::make('time')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->heading('Tin tức mới nhất')
            ->description('5 tin tức được thu thập gần đây nhất')
            ->paginated(false);
    }
}

