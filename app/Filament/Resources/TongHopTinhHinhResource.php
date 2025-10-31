<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TongHopTinhHinhResource\Pages;
use App\Models\TongHopTinhHinh;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Joaopaulolndev\FilamentGeneralSettings\Models\GeneralSetting;
use OpenAI;

class TongHopTinhHinhResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = TongHopTinhHinh::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationGroup = 'Tổng Hợp';
    protected static ?string $navigationLabel = 'Tổng hợp tình hình';
    protected static ?string $modelLabel = 'Tổng hợp tình hình';
    protected static ?string $slug = 'tong-hop-tinh-hinh';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('link')
                    ->label('Link bài viết')
                    ->maxLength(150)
                    ->required()
                    ->unique(ignoreRecord: true),

                Forms\Components\Select::make('id_muctieu')
                    ->label('Mục tiêu')
                    ->relationship('mucTieu', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        $sourceKey = 'options.type.' . $record->type;
                        $source = Lang::has($sourceKey) ? trans($sourceKey) : 'Không rõ';
                        return $source . ' - ' . ($record->name ?? 'Không rõ');
                    }),

                Forms\Components\Radio::make('phanloai')
                    ->label('Phân loại tin tức')
                    ->options(__('options.phanloai'))
                    ->default(1)
                    ->required()
                    ->columns(2)
                    ->extraAttributes(['style' => 'margin-left: 50px;']),

                Forms\Components\FileUpload::make('pic')
                    ->label('Ảnh chụp màn hình')
                    ->image()
                    ->disk('public')
                    ->directory(fn() => 'uploads/tinhhinh/' . now()->format('Ymd'))
                    ->maxSize(20480)
                    ->nullable()
                    ->optimize('webp'),

                Forms\Components\Grid::make(10)
                    ->schema([
                        Forms\Components\Textarea::make('contents_text')
                            ->label('Nội dung bài viết')
                            ->rows(6)
                            ->hint('Nhấn để tự động tóm tắt')
                            ->hintAction(
                                Forms\Components\Actions\Action::make('generateSummary')
                                    ->label('Tóm tắt')
                                    ->icon('heroicon-m-sparkles')
                                    ->tooltip('Tóm tắt bằng AI')
                                    ->color('success')
                                    ->requiresConfirmation(false)
                                    ->action(function ($state, $set, $get) {
                                        $content = $state;

                                        if (empty($content)) {
                                            static::sendErrorNotification('Nội dung bài viết trống.')->send();
                                            return;
                                        }

                                        $soKyTu = (int)$get('so_ky_tu') ?: 100;
                                        $summary = static::generateSummary($content, $soKyTu);
                                        $set('sumary', $summary);
                                    })
                            )
                            ->columnSpan(4),

                        Forms\Components\TextInput::make('so_ky_tu')
                            ->numeric()
                            ->default(100)
                            ->minValue(10)
                            ->maxValue(1000)
                            ->suffix('ký tự')
                            ->label('Số lượng ký tự tóm tắt ')
                            ->columnSpan(2)
                            ->dehydrated(false) // không lưu vào DB
                            ->afterStateHydrated(function ($component, $state) {
                                // Nếu mở form edit mà không có dữ liệu -> gán mặc định 100
                                if (blank($state)) {
                                    $component->state(100);
                                }
                            }),
                        Forms\Components\Textarea::make('sumary')
                            ->label('Tóm tắt nội dung')
                            ->maxLength(1000)
                            ->rows(6)
                            ->columnSpan(4)
                    ]),

                Forms\Components\Select::make('id_user')
                    ->label('Người chia sẻ')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->default(auth()->id()),


                Forms\Components\DateTimePicker::make('time')
                    ->label('Thời gian ghi nhận')
                    ->seconds(false)
                    ->default(now()),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('time', 'desc')
            ->columns([
                // STT
                Tables\Columns\TextColumn::make('stt')
                    ->label('STT')
                    ->rowIndex(),

                // Nội dung tóm tắt
                Tables\Columns\TextColumn::make('link')
                    ->label('Link bài viết')
                    ->url(fn($record) => $record->link, true) // click được, mở tab mới
                    ->limit(50) // cắt ngắn link cho gọn
                    ->wrap()
                    ->description(fn($record) => $record->sumary ?? '') // tóm tắt hiển thị dưới
                    ->sortable()
                    ->searchable(['link', 'contents_text', 'sumary']),

                // Mục tiêu (liên kết từ bảng muc_tieus)
                Tables\Columns\TextColumn::make('muctieu.name')
                    ->label('Mục tiêu')
                    ->url(fn($record) => $record->muctieu?->link, true) // link sang bài gốc
                    ->color('primary')
                    ->wrap(),

                // Hình ảnh (thumbnail)
                Tables\Columns\ImageColumn::make('pic')
                    ->label('Hình ảnh')
                    ->disk('public')
                    ->height(80)
                    ->width(120)
                    ->action(
                        Tables\Actions\Action::make('Xem ảnh')
                            ->modalHeading('Xem ảnh')
                            ->modalContent(fn($record) => view('filament.modals.preview-image', [
                                'url' => Storage::disk('public')->url($record->pic)
                            ])
                            )
                            ->modalSubmitAction(false)
                    ),

                // Người ghi nhận (user)
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Người ghi nhận')
                    ->sortable(),

                // Thời gian
                Tables\Columns\TextColumn::make('time')
                    ->label('Time')
                    ->dateTime('H:i:s d/m/Y')
                    ->sortable(),

                // Phân loại
                Tables\Columns\TextColumn::make('phanloai')
                    ->label('Phân loại')
                    ->formatStateUsing(
                        fn($state) => trans("options.phanloai.$state") !== "options.phanloai.$state"
                            ? trans("options.phanloai.$state")
                            : 'Chưa xác định'
                    )
                    ->badge() // hiển thị badge màu đẹp
                    ->color(fn($state) => match ($state) {
                        1, 2 => 'danger',
                        3, 4 => 'warning',
                        5 => 'info',
                        default => 'gray',
                    }),

            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTongHopTinhHinhs::route('/'),
            'create' => Pages\CreateTongHopTinhHinh::route('/create'),
            'view' => Pages\ViewTongHopTinhHinh::route('/{record}'),
            'edit' => Pages\EditTongHopTinhHinh::route('/{record}/edit'),
        ];
    }

    public static function sendErrorNotification(string $message): Notification
    {
        return Notification::make()
            ->title('Thất bại')
            ->danger()
            ->body($message)
            ->send();
    }

    public static function sendSuccessNotification(string $message): Notification
    {
        return Notification::make()
            ->title('Thành công')
            ->success()
            ->body($message)
            ->send();
    }

    public static function generateSummary($content, $soKyTu): string
    {
        // Get API config
        $apiConfig = static::getApiConfiguration();

        if (!$apiConfig['valid']) {
            static::sendErrorNotification($apiConfig['message']);
        }
        // Build AI prompt
        $messages = static::buildAIPrompt($content, $apiConfig['promptContent'], $soKyTu);

        try {
            $client = OpenAI::client($apiConfig['key']);
            $response = $client->chat()->create([
                'model' => $apiConfig['model'],
                'messages' => $messages,
                'temperature' => (int)$apiConfig['temperature'],
                'max_tokens' => (int)$apiConfig['max_tokens'],
            ]);

            $summary = $response->choices[0]->message->content ?? 'Không thể tóm tắt nội dung.';
        } catch (\Exception $e) {
            return 'Lỗi trong quá trình tóm tắt: ' . $e->getMessage();
        }
        // Thông báo thành công
        static::sendSuccessNotification('Tóm tắt nội dung thành công!');
        return $summary;

    }

    public static function getApiConfiguration(): array
    {
        $settings = GeneralSetting::first();

        return [
            'key' => $settings->more_configs['key_api'] ?? null,
            'model' => $settings->more_configs['model_api'] ?? 'gpt-3.5-turbo',
            'temperature' => $settings->more_configs['temperature_api'] ?? '0.5',
            'max_tokens' => $settings->more_configs['max_tokens_api'] ?? '300',
            'promptContent' => $settings->more_configs[$settings->more_configs['select_prompt'] ?? 'prompt_1'] ?? '',
            'valid' => !empty($settings->more_configs['key_api']),
            'message' => empty($settings) ? 'Cấu hình hệ thống không tồn tại' : 'Cấu hình API không hợp lệ'
        ];
    }

    public static function buildAIPrompt(?string $content, string $prompt, int $soKyTu): array
    {
        $prompt = str_replace('{so_ky_tu}', $soKyTu, $prompt);
        $messages = [
            [
                'role' => 'system',
                'content' => $prompt
            ],
            [
                'role' => 'user',
                'content' => $content
            ],
        ];

        return $messages;
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
