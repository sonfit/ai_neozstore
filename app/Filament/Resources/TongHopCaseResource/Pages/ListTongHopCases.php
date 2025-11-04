<?php

namespace App\Filament\Resources\TongHopCaseResource\Pages;

use App\Filament\Resources\TongHopCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTongHopCases extends ListRecords
{
    protected static string $resource = TongHopCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
