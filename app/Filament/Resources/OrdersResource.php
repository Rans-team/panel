<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OrdersResource\Pages;
use App\Models\Orders;
use Coolsam\FilamentFlatpickr\Forms\Components\Flatpickr;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use LaraZeus\Quantity\Components\Quantity;

final class OrdersResource extends Resource
{
    protected static ?string $model = Orders::class;

    protected static ?string $navigationLabel = '注文履歴';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('注文情報')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('基本情報')
                        ->schema([
                            Forms\Components\Card::make()->schema([
                                Forms\Components\Select::make('id')
                                    ->label('商品名')
                                    ->relationship('product', 'name')
                                    ->required(),
                                Flatpickr::make('created_at')
                                    ->label('注文日')
                                    ->required(),
                            ]),
                        ]),
                    Forms\Components\Tabs\Tab::make('注文明細')
                        ->schema([
                            Forms\Components\Card::make()->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Quantity::make('quantity')
                                        ->label('個数')
                                        ->default(0)
                                        ->minValue(0)
                                        ->required(),
                                    Quantity::make('total_price')
                                        ->label('合計金額')
                                        ->default(0)
                                        ->minValue(0)
                                        ->required(),
                                ]),
                            ]),
                        ]),
                    Forms\Components\Tabs\Tab::make('購入オプション')
                        ->schema([
                            Forms\Components\Card::make()->schema([
                                Forms\Components\Textarea::make('options')
                                    ->label('購入オプション')
                                    ->disabled()
                                    ->rows(5)
                                    ->afterStateHydrated(function (\Filament\Forms\Components\Field $component, $state) {
                                        $formatted = self::formatOptionsForForm($state);
                                        $component->state($formatted);
                                    }),
                            ]),
                        ]),
                ])
                ->columnSpan(2),
        ]);
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('商品名')
                ->sortable(),

            Tables\Columns\TextColumn::make('quantity')
                ->label('個数')
                ->sortable(),

            Tables\Columns\ImageColumn::make('image')
                ->label('商品画像')
                ->size(50)
                ->sortable()
                ->placeholder('No Image'),

            Tables\Columns\TextColumn::make('total_price')
                ->label('合計金額')
                ->sortable()
                ->money('JPY'),

            Tables\Columns\TextColumn::make('options')
                ->label('購入オプション')
                ->formatStateUsing(function ($state) {
                    return self::formatOptionsForTable($state);
                }),

            Tables\Columns\TextColumn::make('created_at')
                ->label('注文日')
                ->sortable()
                ->date('M d, Y'),
        ])
            ->filters([
                Tables\Filters\Filter::make('name')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('商品名'),
                    ])
                    ->query(function ($query, array $data): mixed {
                        return $query->when(
                            $data['name'],
                            fn ($query, $name) => $query->where('name', 'like', "%{$name}%")
                        );
                    }),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Flatpickr::make('from')
                            ->label('開始日'),
                        Flatpickr::make('until')
                            ->label('終了日'),
                    ])
                    ->query(function ($query, array $data): mixed {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($query) => $query->whereDate('created_at', '>=', $data['from'])
                            )
                            ->when(
                                $data['until'],
                                fn ($query) => $query->whereDate('created_at', '<=', $data['until'])
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    private static function formatOptionsForForm($state)
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $state = $decoded;
            }
        }
        if (is_array($state)) {
            if (array_is_list($state)) {
                $state = collect($state)
                    ->map(function ($item) {
                        if (is_array($item) && isset($item['option_name'], $item['price'])) {
                            return sprintf('%s: %s', $item['option_name'], $item['price']);
                        }

                        return '';
                    })
                    ->filter()
                    ->implode(', ');
            } else {
                $state = collect($state)
                    ->map(function ($value, $key) {
                        if (is_array($value)) {
                            $value = $value['price'] ?? json_encode($value);
                        }

                        return sprintf('%s: %s', $key, $value);
                    })
                    ->implode(', ');
            }
        }

        return $state;
    }

    private static function formatOptionsForTable($state): string
    {
        $limit = 20;
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $state = $decoded;
            }
        }
        if (is_array($state)) {
            if (array_is_list($state)) {
                return collect($state)
                    ->map(function ($item) use ($limit) {
                        return is_array($item) && isset($item['option_name'])
                            ? self::shortenText($item['option_name'], $limit)
                            : '';
                    })
                    ->filter()
                    ->implode(', ');
            } else {
                return collect($state)
                    ->map(function ($value, $key) use ($limit) {
                        return self::shortenText($key, $limit);
                    })
                    ->implode(', ');
            }
        }

        return (string) $state;
    }

    private static function shortenText(string $text, int $limit = 20): string
    {
        return (mb_strlen($text) > $limit) ? mb_substr($text, 0, $limit).'…' : $text;
    }
}
