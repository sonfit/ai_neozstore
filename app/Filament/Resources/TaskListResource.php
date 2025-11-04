<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskListResource\Pages;
use App\Models\TaskList;
use App\Models\ThuTin;
use App\Models\TongHopTinhHinh;
use App\Services\FunctionHelp;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class TaskListResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = TaskList::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Tổng Hợp';
    protected static ?string $navigationLabel = 'Case';
    protected static ?string $modelLabel = 'Danh sách case';
    protected static ?string $slug = 'danh-sach-case';
    protected static ?int $navigationSort = 3;

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
                // Chỉ hiện những case có status = 0
                $query->where('status', 0);
                $query->withCount('thuTins');
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Người tạo')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('thu_tins_count')
                    ->label('Thu tin')
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
                Tables\Actions\Action::make('tong_hop')
                    ->label('Tổng hợp')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Xác nhận tổng hợp')
                    ->modalDescription('Bạn có chắc chắn muốn tổng hợp case này? Tất cả thu tin trong case sẽ được duplicate sang tổng hợp tình hình.')
                    ->modalSubmitActionLabel('Xác nhận')
                    ->action(function (TaskList $record) {
                        try {
                            // Lấy tất cả thu tin trong case
                            $thuTins = $record->thuTins;
                            
                            if ($thuTins->isEmpty()) {
                                Notification::make()
                                    ->title('Thất bại')
                                    ->danger()
                                    ->body('Case này không có thu tin nào để tổng hợp.')
                                    ->send();
                                return;
                            }

                            $tongHopTinhHinhs = [];
                            
                            foreach ($thuTins as $thuTin) {
                                // Copy media từ thutin sang tonghop
                                $newPic = [];
                                if ($thuTin->pic && is_array($thuTin->pic)) {
                                    foreach ($thuTin->pic as $picPath) {
                                        if (Storage::disk('public')->exists($picPath)) {
                                            // Tạo đường dẫn mới trong thư mục tonghop
                                            $date = now()->format('Ymd');
                                            $directory = "uploads/tonghop/{$date}";
                                            Storage::disk('public')->makeDirectory($directory);
                                            
                                            // Lấy tên file và extension
                                            $fileName = basename($picPath);
                                            $newPath = $directory . '/' . time() . '_' . uniqid('', true) . '_' . $fileName;
                                            
                                            // Copy file
                                            Storage::disk('public')->copy($picPath, $newPath);
                                            $newPic[] = $newPath;
                                        }
                                    }
                                }
                                
                                // Tạo tong hop tinh hinh mới
                                $tongHopTinhHinh = TongHopTinhHinh::create([
                                    'link' => $thuTin->link,
                                    'contents_text' => $thuTin->contents_text,
                                    'pic' => $newPic,
                                    'phanloai' => $thuTin->phanloai,
                                    'diem' => $thuTin->diem,
                                    'id_bot' => $thuTin->id_bot,
                                    'id_user' => $thuTin->id_user,
                                    'id_muctieu' => $thuTin->id_muctieu,
                                    'time' => $thuTin->time,
                                ]);
                                
                                $tongHopTinhHinhs[] = $tongHopTinhHinh;
                            }
                            
                            // Attach các tong hop tinh hinh vào task list
                            $record->tongHopTinhHinhs()->sync(collect($tongHopTinhHinhs)->pluck('id')->toArray());
                            
                            // Cập nhật status của task list thành 1
                            $record->update(['status' => 1]);
                            
                            Notification::make()
                                ->title('Thành công')
                                ->success()
                                ->body('Đã tổng hợp ' . count($tongHopTinhHinhs) . ' thu tin thành công.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Thất bại')
                                ->danger()
                                ->body('Có lỗi xảy ra: ' . $e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TaskListResource\RelationManagers\ThuTinsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaskLists::route('/'),
            'create' => Pages\CreateTaskList::route('/create'),
            'edit' => Pages\EditTaskList::route('/{record}/edit'),
        ];
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
