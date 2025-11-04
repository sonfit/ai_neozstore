<?php

namespace App\Filament\Resources\ThuTinResource\Pages;

use App\Filament\Resources\ThuTinResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateThuTin extends CreateRecord
{
    protected static string $resource = ThuTinResource::class;

    protected function afterCreate(): void
    {
        // Tính lại điểm sau khi tags đã được sync
        $record = $this->record;
        $tongDiemTags = $record->tags()->sum('diem');
        // Lấy điểm cơ sở từ form data hoặc tính từ điểm hiện tại
        $diemCoSo = $this->data['diem_co_so'] ?? ($record->diem ?? 0) - $tongDiemTags;
        $record->diem = max(0, $diemCoSo + $tongDiemTags);
        $record->saveQuietly();
    }
}
