<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ThuTinResource\Pages;
use App\Models\TaskList;
use App\Models\ThuTin;
use App\Services\FunctionHelp;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ThuTinResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = ThuTin::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'Tổng Hợp';
    protected static ?string $navigationLabel = 'Thu tin';
    protected static ?string $modelLabel = 'Thu tin';
    protected static ?string $slug = 'thu-tin';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('id_user')
                    ->label('Người chia sẻ')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->default(auth()->id()),

                Forms\Components\DateTimePicker::make('time')
                    ->label('Thời gian ghi nhận')
                    ->seconds(false)
                    ->default(now()),

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

                Forms\Components\TextInput::make('diem')
                    ->label('Điểm')
                    ->helperText(new HtmlString('
                        <strong>Điểm từ 0–100:</strong><br>
                        - điểm ≥ 100 → level 5<br>
                        - điểm ≥ 70 → level 4<br>
                        - điểm ≥ 40 → level 3<br>
                        - điểm ≥ 20 → level 2<br>
                        - điểm < 20 → level 1
                    '))
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->required(),

                Forms\Components\Textarea::make('contents_text')
                    ->label('Nội dung bài viết')
                    ->rows(10),

                Forms\Components\FileUpload::make('pic')
                    ->label('Ảnh chụp màn hình')
                    ->image()
                    ->disk('public')
                    ->directory(fn() => 'uploads/thutin/' . now()->format('Ymd'))
                    ->maxSize(512000)
                    ->nullable()
                    ->multiple()
                    ->imagePreviewHeight('200')
                    ->panelLayout('grid')
                    ->reorderable()
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'video/mp4',
                        'video/avi',
                        'video/mpeg',
                        'video/quicktime',
                    ]),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();
                $query->with(['tasklists.user'])->withCount('tasklists');

                // Nếu user có quyền admin hoặc super_admin thì xem tất cả
                if (FunctionHelp::isAdminUser()) {
                    return $query;
                }

                // Nếu user có quyền user thì chỉ xem tin thuộc mục tiêu mà user theo dõi
                if (FunctionHelp::isUser()) {
                    $mucTieuIds = $user->mucTieus()->pluck('muc_tieus.id')->toArray();

                    // Nếu user chưa theo dõi mục tiêu nào thì không hiển thị gì
                    if (empty($mucTieuIds)) {
                        return $query->whereRaw('1 = 0'); // Always false condition
                    }

                    return $query->whereIn('id_muctieu', $mucTieuIds);
                }

                return $query;
            })
            ->defaultSort('time', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('stt')
                    ->label('STT')
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('link')
                    ->label('Link bài viết')
                    ->url(fn($record) => $record->link, true)
                    ->limit(50) // cắt ngắn link cho gọn
                    ->wrap()
                    ->description(fn($record) => $record->contents_text ? Str::limit($record->contents_text, 100) : '')
                    ->tooltip(fn($record) => $record->contents_text ?? '')
                    ->sortable()
                    ->searchable(['link', 'contents_text']),

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

//                 Phân loại
                Tables\Columns\TextColumn::make('phanloai')
                    ->label('Phân loại')
                    ->formatStateUsing(
                        fn($state) => trans("options.phanloai.$state") !== "options.phanloai.$state"
                            ? trans("options.phanloai.$state")
                            : 'Chưa xác định'
                    )
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        1, 2 => 'danger',
                        3, 4 => 'warning',
                        5 => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('diem')
                    ->label('Mức độ')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        $level = FunctionHelp::diemToLevel($state);
                        return "Level {$level} ({$state} điểm)";
                    })
                    ->color(function ($state) {
                        $level = FunctionHelp::diemToLevel($state);
                        return FunctionHelp::levelBadgeColor($level);
                    })
                    ->sortable(),

                Tables\Columns\TagsColumn::make('tags')
                    ->label('Tags')
                    ->getStateUsing(function ($record) {
                        $tags = $record->tags->pluck('tag'); // Lấy danh sách tag name
                        $limit = 3;

                        if ($tags->count() > $limit) {
                            $extraCount = $tags->count() - $limit;
                            return $tags->take($limit)->push("+{$extraCount} Tag");
                        }
                        return $tags;
                    })
                    ->separator(', ')
                    ->badge()
                    ->sortable(query: function ($query, $direction) {
                        return $query->withCount('tags')->orderBy('tags_count', $direction);
                    })
                    ->color(function ($record, $state) {
                        $colors = ['primary', 'success', 'danger', 'info'];
                        $index = $record->tags->search(function ($item) use ($state) {
                                return in_array($item->tag, (array)$state);
                            }) % count($colors);
                        return $colors[$index];
                    }),

                // Người ghi nhận (user)
                Tables\Columns\TextColumn::make('bot.ten_bot')
                    ->label('Bot')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Thời gian
                Tables\Columns\TextColumn::make('time')
                    ->label('Thời gian')
                    ->dateTime('H:i:s d/m/Y')
                    ->sortable()
                    ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->format('H:i:s d/m/Y'))
                    ->color(fn($state) => FunctionHelp::timeBadgeColor($state)) // Đảm bảo $state là giá trị gốc
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('bot_id')
                    ->label('Bot')
                    ->relationship('bot', 'ten_bot'),

                SelectFilter::make('muctieu_id')
                    ->label('Mục tiêu')
                    ->relationship('muctieu', 'name'),

                SelectFilter::make('phanloai')
                    ->label('Phân loại')
                    ->options(trans('options.phanloai')),

                Tables\Filters\Filter::make('diem')
                    ->form([
                        Forms\Components\TextInput::make('diem_from')
                            ->label('Điểm từ')
                            ->numeric()
                            ->placeholder('0'),
                        Forms\Components\TextInput::make('diem_to')
                            ->label('Điểm đến')
                            ->numeric()
                            ->placeholder('100'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['diem_from'], fn($query, $from) => $query->where('diem', '>=', $from))
                            ->when($data['diem_to'], fn($query, $to) => $query->where('diem', '<=', $to));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('tasklist')
                    ->label('Case')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color(fn(ThuTin $record) => ($record->tasklist_count ?? $record->tasklists()->count()) > 0 ? 'success' : 'gray')
                    ->tooltip(function (ThuTin $record) {
                        $names = $record->tasklists?->pluck('name') ?? collect();
                        return $names->isNotEmpty() ? $names->join(', ') : 'Chưa có';
                    })
                    ->form(function () {
                        return [
                            Forms\Components\TagsInput::make('foreign_names')
                                ->label('Danh khác case của người dùng khác')
                                ->disabled()
                                ->dehydrated(false)
                                ->visible(function (\Filament\Forms\Get $get) {
                                    $user = auth()->user();
                                    $isAdmin = $user && FunctionHelp::isAdminUser();
                                    return !$isAdmin && filled($get('foreign_names'));
                                }),
                            Forms\Components\Select::make('tasklist_ids')
                                ->label('Chọn case')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->autofocus(false)
                                ->options(function () {
                                    $user = auth()->user();
                                    $isAdmin = FunctionHelp::isAdminUser();

                                    return TaskList::query()
                                        ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
                                        ->with('user')
                                        ->latest()
                                        ->get()
                                        ->mapWithKeys(function ($b) use ($isAdmin) {
                                            $date = $b->created_at?->format('d/m/Y H:i');
                                            $label = $isAdmin
                                                ? ($b->name . ' - (' . ($b->user?->name ?? 'user') . ') - ' . $date)
                                                : ($b->name . ' - ' . $date);
                                            return [$b->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->getSearchResultsUsing(function (string $search) {
                                    $user = auth()->user();
                                    $isAdmin = FunctionHelp::isAdminUser();
                                    return TaskList::query()
                                        ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
                                        ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
                                        ->with('user')
                                        ->latest()
                                        ->get()
                                        ->mapWithKeys(function ($b) use ($isAdmin) {
                                            $date = $b->created_at?->format('d/m/Y H:i');
                                            $label = $isAdmin
                                                ? ($b->name . ' - (' . ($b->user?->name ?? 'user') . ') - ' . $date)
                                                : ($b->name . ' - ' . $date);
                                            return [$b->id => $label];
                                        })
                                        ->toArray();
                                })
                                ->getOptionLabelsUsing(function (array $values) {
                                    $isAdmin = FunctionHelp::isAdminUser();
                                    $tasklists = TaskList::with('user')->whereIn('id', $values)->get();
                                    $labels = [];
                                    foreach ($tasklists as $b) {
                                        $date = $b->created_at?->format('d/m/Y H:i');
                                        $labels[$b->id] = $isAdmin
                                            ? ($b->name . ' - (' . ($b->user?->name ?? 'user') . ') - ' . $date)
                                            : ($b->name . ' - ' . $date);
                                    }
                                    return $labels;
                                })
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Tên công việc')
                                        ->required()
                                        ->maxLength(255),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $user = auth()->user();
                                    $tasklist = TaskList::create([
                                        'user_id' => $user->id,
                                        'name' => $data['name'] ?? '',
                                    ]);
                                    return $tasklist->id;
                                })
                                ->nullable(),
                        ];
                    })
                    ->mountUsing(function (\Filament\Forms\ComponentContainer $form, ThuTin $record) {
                        $tasklists = $record->tasklists()->with('user')->get();
                        $user = auth()->user();
                        $isAdmin = $user && FunctionHelp::isAdminUser();
                        if ($isAdmin) {
                            $form->fill([
                                'tasklist_ids' => $tasklists->pluck('id')->toArray(),
                                'foreign_names' => null,
                            ]);
                            return;
                        }
                        $own = $tasklists->where('user_id', $user->id);
                        $foreign = $tasklists->where('user_id', '!=', $user->id);
                        $ownIds = $own->pluck('id')->toArray();
                        $foreignNames = $foreign->map(function ($b) {
                            $date = $b->created_at?->format('d/m/Y H:i');
                            return $b->name . ' - ' . $date;
                        })->values()->toArray();

                        $form->fill([
                            'tasklist_ids' => $ownIds,
                            'foreign_names' => $foreignNames,
                        ]);
                    })
                    ->action(function (ThuTin $record, array $data) {
                        $user = auth()->user();
                        $selectedIds = collect($data['tasklist_ids'] ?? [])->map(fn($v) => (int)$v)->unique()->values();
                        $isAdmin = FunctionHelp::isAdminUser();

                        if ($isAdmin) {
                            $record->tasklists()->sync($selectedIds);
                            return;
                        }

                        $currentUserId = $user->id;
                        $existingOwn = $record->tasklists()
                            ->where('task_lists.user_id', $currentUserId)
                            ->pluck('task_lists.id');


                        $selectedOwn = TaskList::query()
                            ->where('user_id', $currentUserId)
                            ->whereIn('id', $selectedIds)
                            ->pluck('id');

                        $toAttach = $selectedOwn->diff($existingOwn);
                        $toDetach = $existingOwn->diff($selectedOwn);

                        if ($toAttach->isNotEmpty()) {
                            $record->tasklists()->syncWithoutDetaching($toAttach->all());
                        }
                        if ($toDetach->isNotEmpty()) {
                            $record->tasklists()->detach($toDetach->all());
                        }
                    })
                    ->successNotificationTitle('Đã cập nhật công việc')
                    ->visible(fn() => auth()->check())
                    ->modalHeading('Thêm vào công việc')
                    ->modalSubmitActionLabel('Lưu'),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListThuTins::route('/'),
            'create' => Pages\CreateThuTin::route('/create'),
            'view' => Pages\ViewThuTin::route('/{record}'),
            'edit' => Pages\EditThuTin::route('/{record}/edit'),
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
