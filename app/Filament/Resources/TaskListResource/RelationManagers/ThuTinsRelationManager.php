<?php

namespace App\Filament\Resources\TaskListResource\RelationManagers;

use App\Filament\Resources\ThuTinResource;
use App\Services\FunctionHelp;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ThuTinsRelationManager extends RelationManager
{
    protected static string $relationship = 'thuTins';

    protected static ?string $title = 'Thu tin';




    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form
            ->schema([
                // no create form; we only attach existing ThuTin here
            ]);
    }

    public function table(Table $table): Table
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
            ->columns([
                Tables\Columns\TextColumn::make('link')
                    ->label('Link bài viết')
                    ->url(fn($record) => $record->link, true)
                    ->limit(50)
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

                Tables\Columns\TextColumn::make('time')
                    ->label('Thời gian')
                    ->dateTime('H:i:s d/m/Y')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->multiple()
                    ->recordSelectSearchColumns(['link', 'contents_text'])
                    ->recordTitleAttribute('link'),
            ])
            ->actions([
				Tables\Actions\Action::make('edit')
					->label('Chỉnh sửa')
					->icon('heroicon-o-pencil-square')
					->url(fn($record) => ThuTinResource::getUrl('edit', ['record' => $record]))
					->openUrlInNewTab(),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}


