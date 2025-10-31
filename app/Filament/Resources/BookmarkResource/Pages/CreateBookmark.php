<?php

namespace App\Filament\Resources\BookmarkResource\Pages;

use App\Filament\Resources\BookmarkResource;
use App\Services\FunctionHelp;
use Filament\Resources\Pages\CreateRecord;

class CreateBookmark extends CreateRecord
{
    protected static string $resource = BookmarkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!FunctionHelp::isAdminUser()) {
            $data['user_id'] = auth()->id();
        }
        return $data;
    }
}


