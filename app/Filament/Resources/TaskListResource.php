<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskListResource\Pages;
use App\Models\TaskList;
use App\Services\FunctionHelp;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
