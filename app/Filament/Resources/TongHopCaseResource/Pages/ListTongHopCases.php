<?php

namespace App\Filament\Resources\TongHopCaseResource\Pages;

use App\Filament\Resources\TongHopCaseResource;
use App\Services\CaseReportService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTongHopCases extends ListRecords
{
    protected static string $resource = TongHopCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('report')
                ->label('Báo cáo')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->modalHeading('Báo cáo case')
                ->modalWidth('6xl')
                ->modalSubmitAction(false)
                ->form([
                    Forms\Components\DatePicker::make('from_date')
                        ->label('Từ ngày')
                        ->displayFormat('d/m/Y')
                        ->default(now()->subDays(30))
                        ->required(),
                    Forms\Components\DatePicker::make('to_date')
                        ->label('Đến ngày')
                        ->displayFormat('d/m/Y')
                        ->default(now())
                        ->required(),
                    Forms\Components\Textarea::make('report_content')
                        ->label('Nội dung báo cáo')
                        ->rows(15)
                        ->disabled()
                        ->dehydrated(false)
                        ->reactive(),
                ])
                ->extraModalFooterActions([
                    Actions\Action::make('regenerateReport')
                        ->label('Tạo lại báo cáo')
                        ->icon('heroicon-m-arrow-path')
                        ->color('warning')
                        ->action(function ($livewire) {
                            $formData = $livewire->mountedActionsData[0] ?? [];


                            if (empty($formData['from_date']) || empty($formData['to_date'])) {
                                Notification::make()
                                    ->title('Vui lòng chọn khoảng thời gian')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $reportService = app(CaseReportService::class);

                            $fromDate = Carbon::parse($formData['from_date']);
                            $toDate = Carbon::parse($formData['to_date']);

                            $query = $this->getTableQuery();
                            $query->where('created_at', '>=', $fromDate->startOfDay())
                                  ->where('created_at', '<=', $toDate->endOfDay());

                            $caseIds = $query->pluck('id')->toArray();

                            $reportContent = $reportService->generateReport($caseIds, $fromDate, $toDate);


                            // Update form data
                            $livewire->mountedActionsData[0]['report_content'] = $reportContent;
                            $livewire->dispatch('$refresh');
                            Notification::make()
                                ->title('Báo cáo đã được tạo')
                                ->success()
                                ->send();
                        }),

                ]),
        ];
    }
}
