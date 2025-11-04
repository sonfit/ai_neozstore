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
                    ->requiresConfirmation(false)
                    ->action(function (TaskList $record) {
                        /** @var SummarizeService $summarizer */
                        $summarizer = App::make(SummarizeService::class);

                        $items = $record->tongHopTinhHinhs()
                            ->select(['contents_text', 'pic'])
                            ->get()
                            ->map(function ($r) {
                                return [
                                    'contents_text' => $r->contents_text,
                                    'pic' => $r->pic,
                                ];
                            })
                            ->toArray();

                        if (empty($items)) {
                            return;
                        }

                        // Let the service handle tokens and completeness; avoid over-truncation here
                        $summary = $summarizer->summarizeCaseFromItems($items, null);
                        if ($summary) {
                            $record->update(['sumary' => $summary]);
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

