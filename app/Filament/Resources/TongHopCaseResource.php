<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TongHopCaseResource\Pages;
use App\Filament\Resources\TongHopCaseResource\RelationManagers;
use App\Models\TaskList;
use App\Models\TongHopCase;
use App\Services\FunctionHelp;
use App\Services\SummarizeService;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\App;
use Filament\Notifications\Notification;
use App\Services\CaseReportService;
use Carbon\Carbon;

class TongHopCaseResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = TaskList::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationGroup = 'Tổng Hợp';
    protected static ?string $navigationLabel = 'Tổng hợp case';
    protected static ?string $modelLabel = 'Danh sách case đã tổng hợp';
    protected static ?string $slug = 'tong-hop-case';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        $isAdmin = FunctionHelp::isAdminUser();
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Tên')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('user_id')
                    ->label('Người tạo')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->default(auth()->id())
                    ->hidden(!$isAdmin)
                    ->required(),

                Forms\Components\Textarea::make('sumary')
                    ->label('Tóm tắt nội dung')
                    ->maxLength(1000)
                    ->rows(6)
                    ->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();
                if ($user && !FunctionHelp::isAdminUser()) {
                    $query->where('user_id', $user->id);
                }
                // Chỉ hiện những case có status = 1
                $query->where('status', 1);
                $query->withCount('tongHopTinhHinhs');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Người tạo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sumary')
                    ->label('Tóm tắt')
                    ->limit(100)
                    ->toggleable()
                    ->tooltip(fn($record) => $record->sumary ?? '')
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('tong_hop_tinh_hinhs_count')
                    ->label('Tổng hợp tình hình')
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('H:i:s d/m/Y')
                    ->sortable(),
            ])
            ->filters([
            ])
            ->actions([
                Tables\Actions\Action::make('generateSummary')
                    ->label('Tóm tắt case')
                    ->icon('heroicon-m-sparkles')
                    ->color('success')
                    ->modalHeading('Tóm tắt case')
                    ->modalWidth('4xl')
                    ->modalSubmitActionLabel('Lưu') // Filament tự tạo nút "Lưu" + tự đóng modal
                    ->modalCancelActionLabel('Hủy')
                    ->form([
                        Forms\Components\Textarea::make('sumary')
                            ->label('Tóm tắt nội dung')
                            ->rows(10)
                            ->reactive(),

                        Forms\Components\TextInput::make('max_chars')
                            ->label('Giới hạn ký tự')
                            ->numeric()
                            ->minValue(100)
                            ->maxValue(300)
                            ->default(200)
                            ->suffix('ký tự')
                            ->helperText('Giới hạn độ dài tóm tắt mong muốn'),
                    ])

                    ->mountUsing(fn (Forms\ComponentContainer $form, TaskList $record) => $form->fill([
                        'sumary' => $record->sumary,
                        'max_chars' => 200,
                    ]))
                    ->extraModalFooterActions([
                        Tables\Actions\Action::make('regenerateSummary')
                            ->label('Tóm tắt lại')
                            ->icon('heroicon-m-arrow-path')
                            ->color('warning')
                            ->action(function (TaskList $record, array $data, $livewire) {
                                $items = $record->tongHopTinhHinhs()
                                    ->select(['contents_text', 'pic'])
                                    ->get()
                                    ->map(fn($r) => ['contents_text' => $r->contents_text, 'pic' => $r->pic])
                                    ->toArray();

                                if (empty($items)) {
                                    Notification::make()
                                        ->title('Không có dữ liệu để tóm tắt')
                                        ->danger()
                                        ->send();
                                    return;
                                }


                                $summarizer = app(SummarizeService::class);
                                $maxChars = $livewire->mountedTableActionsData[0]['max_chars'] ?? 200;
                                $summary = $summarizer->summarizeCaseFromItems($items, $maxChars, null);


                                if ($summary) {
                                    $livewire->mountedTableActionsData[0]['sumary'] = $summary;
                                    $livewire->dispatch('$refresh');
                                    Notification::make()
                                        ->title('Tóm tắt đã được tạo')
                                        ->body('Nội dung đã được cập nhật. Bạn có thể chỉnh sửa trước khi lưu.')
                                        ->success()
                                        ->send();
                                }
                            }),
                    ])

                    ->action(function (TaskList $record, array $data) {
                        $record->update([
                            'sumary' => $data['sumary'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Đã lưu tóm tắt')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkReport')
                        ->label('Báo cáo')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->modalHeading('Báo cáo các case đã chọn')
                        ->modalWidth('6xl')
                        ->modalSubmitAction(false)
                        ->form([
                            Forms\Components\Textarea::make('report_content')
                                ->label('Nội dung báo cáo')
                                ->rows(15)
                                ->disabled()
                                ->dehydrated(false)
                                ->reactive(),
                        ])
                        ->mountUsing(function (Forms\ComponentContainer $form, $records) {
                            $caseIds = $records->pluck('id')->toArray();
                            $reportService = app(CaseReportService::class);
                            $reportContent = $reportService->generateReport($caseIds);

                            $form->fill([
                                'report_content' => $reportContent,
                            ]);
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TaskListResource\RelationManagers\TongHopTinhHinhsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTongHopCases::route('/'),
            'create' => Pages\CreateTongHopCase::route('/create'),
            'edit' => Pages\EditTongHopCase::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }


}

