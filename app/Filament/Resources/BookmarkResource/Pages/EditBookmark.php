<?php

namespace App\Filament\Resources\BookmarkResource\Pages;

use App\Filament\Resources\BookmarkResource;
use App\Services\FunctionHelp;
use Filament\Resources\Pages\EditRecord;

class EditBookmark extends EditRecord
{
    protected static string $resource = BookmarkResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!FunctionHelp::isAdminUser()) {
            // Prevent changing owner if not admin
            unset($data['user_id']);
        }
        return $data;
    }
}


