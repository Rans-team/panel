<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Filament\Resources\UserResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Rawilk\FilamentPasswordInput\Password;
use Wallo\FilamentSelectify\Components\ToggleButton;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationLabel = 'ユーザー管理';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'ユーザー管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->label('ユーザー名'),

                Password::make('password')
                    ->regeneratePassword()
                    ->newPasswordLength(14)
                    ->maxLength(255)
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                    ->required(fn ($livewire) => !isset($livewire->record) || auth()->id() !== $livewire->record->id)
                    ->label('パスワード'),

                ToggleButton::make('is_active')
                    ->label('アカウントの状態')
                    ->offColor('danger')
                    ->onColor('primary')
                    ->offLabel('無効')
                    ->onLabel('有効')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\BooleanColumn::make('is_active')->label('有効'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn ($record) => $record->is_active ? '無効化' : '有効化')
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);
                    })
                    ->color(fn ($record) => $record->is_active ? 'warning' : 'success'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('disable')
                    ->label('選択を一括無効化')
                    ->action(function (\Illuminate\Support\Collection $records) {
                        foreach ($records as $record) {
                            $record->update(['is_active' => false]);
                        }
                    })
                    ->requiresConfirmation(),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
