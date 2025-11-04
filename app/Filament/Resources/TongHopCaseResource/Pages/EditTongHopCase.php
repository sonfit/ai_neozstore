<?php

namespace App\Filament\Resources\TongHopCaseResource\Pages;

use App\Filament\Resources\TongHopCaseResource;
use App\Models\TaskList;
use App\Services\SummarizeService;
use Illuminate\Support\Facades\App;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;

class EditTongHopCase extends EditRecord
{
    protected static string $resource = TongHopCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generateSummary')
                ->label('Tóm tắt')
                ->icon('heroicon-m-sparkles')
                ->color('success')
                ->requiresConfirmation(false)
                ->form([
                    Forms\Components\TextInput::make('max_chars')
                        ->label('Giới hạn ký tự')
                        ->numeric()
                        ->minValue(100)
                        ->maxValue(300)
                        ->default(150)
                        ->suffix('ký tự')
                        ->helperText('Giới hạn độ dài tóm tắt mong muốn')
                ])
                ->action(function (array $data) {
                    /** @var TaskList $record */
                    $record = $this->getRecord();
                    /** @var SummarizeService $summarizer */
                    $summarizer = App::make(SummarizeService::class);

//                    dd($record->tongHopTinhHinhs()->get());

                    $items = $record->tongHopTinhHinhs()
                        ->select(['contents_text', 'sumary', 'pic'])
                        ->get()
                        ->map(fn($r) => [
                            'contents_text' => $r->contents_text,
                            'sumary' => $r->sumary,
                            'pic' => $r->pic,
                        ])
                        ->toArray();

                    if (empty($items)) {
                        return;
                    }

                    $maxChars = isset($data['max_chars']) ? (int) $data['max_chars'] : null;
                    $summary = $summarizer->summarizeCaseFromItems($items, $maxChars, null);
                    if ($summary) {
                        $record->update(['sumary' => $summary]);
                        // Refresh form state so the Textarea 'sumary' shows the new content immediately
                        $record->refresh();
                        $this->fillForm();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
