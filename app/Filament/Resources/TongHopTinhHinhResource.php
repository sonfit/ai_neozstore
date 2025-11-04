<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TongHopTinhHinhResource\Pages;
use App\Models\TongHopTinhHinh;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use App\Services\SummarizeService;
use Illuminate\Support\Str;

class TongHopTinhHinhResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = TongHopTinhHinh::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Tổng Hợp';
    protected static ?string $navigationLabel = 'Tổng hợp tình hình';
    protected static ?string $modelLabel = 'Tổng hợp tình hình';
    protected static ?string $slug = 'tong-hop-tinh-hinh';
    protected static ?int $navigationSort = 5;


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
                                        $content = trim((string) $state);
                                        if ($content === '') {
                                            return;
                                        }


                                        /** @var SummarizeService $summarizer */
                                        $summarizer = App::make(SummarizeService::class);

                                        $maxChars = (int) $get('so_ky_tu') ?: 100;
                                        $pics = $get('pic') ?: [];
                                        $item = [
                                            'contents_text' => $content,
                                            'pic' => $pics,
                                        ];
                                        $summary = $summarizer->summarizeCaseFromItems([$item], $maxChars, null);
                                        if ($summary) {
                                            $set('sumary', $summary);
                                        }
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
                    ->url(fn($record) => $record->link, true)
                    ->limit(50)
                    ->wrap()
                    ->description(fn($record) => Str::limit(
                        $record->sumary ?: $record->contents_text,
                        100 // 👈 giới hạn 100 ký tự
                    ))
                    ->sortable()
                    ->searchable(['link', 'contents_text', 'sumary']),

                // Mục tiêu (liên kết từ bảng muc_tieus)
                Tables\Columns\TextColumn::make('muctieu.name')
                    ->label('Mục tiêu')
                    ->url(fn($record) => $record->muctieu?->link, true) // link sang bài gốc
                    ->color('primary')
                    ->wrap(),

                Tables\Columns\ImageColumn::make('pic')
                    ->label('Hình ảnh')
                    ->disk('public')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText()
                    ->getStateUsing(function ($record) {
                        return collect($record->pic)->map(function ($p) {
                            $url = Storage::disk('public')->url($p);
                            $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));

                            if (in_array($ext, ['mp4', 'webm', 'ogg', 'mov', 'avi'])) {
                                // Nếu là video -> dùng ảnh placeholder
                                return asset('video-placeholder.jpg');
                            }
                            return $url;
                        })->toArray();
                    })
                    ->action(
                        Tables\Actions\Action::make('Xem ảnh')
                            ->modalHeading('Xem media')
                            ->modalContent(fn($record) => view('filament.modals.preview-media', [
                                'urls' => collect($record->pic)->map(fn($p) => Storage::disk('public')->url($p))->toArray()
                            ]))
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

    public static function canCreate(): bool
    {
        return false;
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
