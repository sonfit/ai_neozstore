<?php

namespace App\Filament\Resources\ThuTinResource\Pages;

use App\Filament\Resources\ThuTinResource;
use App\Models\TaskList;
use App\Models\ThuTin;
use App\Services\FunctionHelp;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewThuTin extends ViewRecord
{
    protected static string $resource = ThuTinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('tasklist')
                ->label('Case')
                ->icon('heroicon-o-clipboard-document-list')
                ->color(function () {
                    $record = $this->getRecord();
                    return ($record->tasklist_count ?? $record->tasklists()->count()) > 0 ? 'success' : 'gray';
                })
                ->tooltip(function () {
                    $record = $this->getRecord();
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
                ->mountUsing(function (\Filament\Forms\ComponentContainer $form) {
                    $record = $this->getRecord();
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
                ->action(function (array $data) {
                    $record = $this->getRecord();
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
            Actions\EditAction::make(),
        ];
    }
}
