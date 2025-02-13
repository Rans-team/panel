<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Orders;
use App\Models\Product;
use Exception;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OrderPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string $view = 'filament.pages.order-page';

    protected static ?string $navigationLabel = '注文ページ';

    protected static ?string $title = null;

    public function getTitle(): string
    {
        return '';
    }

    public array $cart = [];

    public int $totalPrice = 0;

    public bool $showPaymentPopup = false;

    public int $paymentAmount = 0;

    public int $changeAmount = 0;

    public bool $showOptionsPopup = false;

    public ?int $selectedProductId = null;

    public array $selectedProductOptions = [];

    public array $selectedOptionIds = [];

    public function mount(): void
    {
        $this->cart = session('cart', []);
        $this->syncCartWithDatabase();
        $this->calculateTotalPrice();
    }

    // 商品をカートに追加
    public function addToCart(int $productId): void
    {
        $product = Product::findOrFail($productId);

        if ($product->stock <= 0) {
            Notification::make()
                ->title('在庫がありません: '.$product->name)
                ->danger()
                ->send();

            return;
        }

        // 同一商品（オプションがない場合）の場合は数量をインクリメント
        foreach ($this->cart as &$item) {
            if ($item['id'] === $product->id && !isset($item['options'])) {
                if ($item['quantity'] < $product->stock) {
                    $item['quantity']++;
                } else {
                    Notification::make()
                        ->title('在庫数を超えています: '.$product->name)
                        ->danger()
                        ->send();
                }
                $this->calculateTotalPrice();
                $this->updateCartSession();

                return;
            }
        }

        $this->cart[] = [
            'id' => $product->id,
            'name' => $product->name,
            'image' => $product->image,
            'price' => $product->price,
            'quantity' => 1,
        ];

        $this->calculateTotalPrice();
        $this->updateCartSession();
    }

    // カート内の指定した商品の数量を更新
    public function updateQuantity(int $index, int|string $quantity): void
    {
        $quantity = (int) $quantity;
        if (!isset($this->cart[$index])) {
            Notification::make()
                ->title('カートに該当する商品が存在しません。')
                ->danger()
                ->send();

            return;
        }

        if ($quantity <= 0) {
            $this->removeFromCart($index);

            return;
        }

        $product = Product::find($this->cart[$index]['id']);
        if (!$product) {
            Notification::make()
                ->title('商品が存在しません。')
                ->danger()
                ->send();
            $this->removeFromCart($index);

            return;
        }

        if ($quantity > $product->stock) {
            Notification::make()
                ->title('在庫数を超えています: '.$product->name)
                ->danger()
                ->send();
            $this->cart[$index]['quantity'] = $product->stock;
        } else {
            $this->cart[$index]['quantity'] = $quantity;
        }

        $this->calculateTotalPrice();
        $this->updateCartSession();
    }

    // カートから指定した商品を削除
    public function removeFromCart(int $index): void
    {
        if (!isset($this->cart[$index])) {
            Notification::make()
                ->title('カートに該当する商品が存在しません。')
                ->danger()
                ->send();

            return;
        }

        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->calculateTotalPrice();
        $this->updateCartSession();
    }

    // カート内の商品情報を最新の状態に同期する
    private function syncCartWithDatabase(): void
    {
        $updatedCart = [];
        foreach ($this->cart as $item) {
            $product = Product::find($item['id']);
            if ($product) {
                $item['name'] = $product->name;
                $item['image'] = $product->image;
                $item['price'] = $product->price;
                if ($product->stock > 0) {
                    $updatedCart[] = $item;
                } else {
                    Notification::make()
                        ->title('商品が在庫切れのためカートから削除されました: '.$product->name)
                        ->warning()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('存在しない商品がカートから削除されました。')
                    ->warning()
                    ->send();
            }
        }
        $this->cart = $updatedCart;
        $this->updateCartSession();
    }

    // カート内の商品の合計金額を計算
    private function calculateTotalPrice(): void
    {
        $this->totalPrice = array_reduce(
            $this->cart,
            fn (int $carry, array $item): int => $carry + (int) ($item['price'] * (int) $item['quantity']),
            0
        );
    }

    // セッションにカートデータを保存
    public function updateCartSession(): void
    {
        session(['cart' => $this->cart]);
    }

    // 商品クリック時、オプションがある場合は選択ポップアップを表示
    public function handleProductClick(int $productId): void
    {
        $product = Product::findOrFail($productId);
        if ($product->options()->count() > 0) {
            $this->selectedProductId = $product->id;
            $this->selectedProductOptions = $product->options()->get()->toArray();
            $this->showOptionsPopup = true;
        } else {
            $this->addToCart($productId);
        }
    }

    // オプション選択後、「確定する」クリックで選択内容を反映しカートに追加
    public function confirmOptionSelection(): void
    {
        if (!$this->selectedProductId) {
            Notification::make()
                ->title('商品が選択されていません。')
                ->danger()
                ->send();

            return;
        }

        $product = Product::findOrFail($this->selectedProductId);

        if (empty($this->selectedOptionIds)) {
            $this->addToCart($this->selectedProductId);

            $this->resetOptionSelection();

            Notification::make()
                ->title('商品がカートに追加されました。')
                ->success()
                ->send();

            return;
        }

        $options = $product->options()->whereIn('id', $this->selectedOptionIds)->get();

        if ($options->isEmpty()) {
            Notification::make()
                ->title('選択されたオプションが存在しません。')
                ->danger()
                ->send();

            return;
        }

        $additionalPrice = $options->sum('price');
        $price = $product->price + $additionalPrice;

        // 既に同じ商品とオプションがカートにあるかをチェックして、あれば数量を増加させる
        foreach ($this->cart as &$item) {
            if ($item['id'] === $product->id && isset($item['options'])) {
                $existingOptionIds = array_map(fn ($opt) => (int) $opt['id'], $item['options']);
                $selectedOptionIds = array_map('intval', $this->selectedOptionIds);
                sort($existingOptionIds);
                sort($selectedOptionIds);
                if ($existingOptionIds === $selectedOptionIds) {
                    if ($item['quantity'] < $product->stock) {
                        $item['quantity']++;
                    } else {
                        Notification::make()
                            ->title('在庫数を超えています: '.$product->name)
                            ->danger()
                            ->send();
                    }
                    $this->calculateTotalPrice();
                    $this->updateCartSession();

                    $this->resetOptionSelection();

                    Notification::make()
                        ->title('商品とオプションがカートに追加されました。')
                        ->success()
                        ->send();

                    return;
                }
            }
        }

        // 既存のカートに同じ商品かつ同じオプションがなかった場合、新規にカートに追加
        $this->cart[] = [
            'id' => $product->id,
            'name' => $product->name,
            'image' => $product->image,
            'price' => $price,
            'quantity' => 1,
            'options' => $options->toArray(),
        ];

        $this->calculateTotalPrice();
        $this->updateCartSession();

        $this->resetOptionSelection();

        Notification::make()
            ->title('商品とオプションがカートに追加されました。')
            ->success()
            ->send();
    }

    // オプション選択をキャンセルしたときの処理
    public function cancelOptionSelection(): void
    {
        $this->resetOptionSelection();
    }

    private function resetOptionSelection(): void
    {
        $this->selectedProductId = null;
        $this->selectedProductOptions = [];
        $this->selectedOptionIds = [];
        $this->showOptionsPopup = false;
    }

    // 支払いポップアップを開く
    public function showPaymentModal(): void
    {
        $this->paymentAmount = 0;
        $this->changeAmount = 0;
        $this->showPaymentPopup = true;
    }

    // おつりを計算
    public function calculateChange(): void
    {
        $this->changeAmount = (int) $this->paymentAmount - $this->totalPrice;
    }

    // 注文を確定
    public function confirmOrder(): void
    {
        $this->syncCartWithDatabase();

        if (empty($this->cart)) {
            Notification::make()
                ->title('カートが空です。')
                ->danger()
                ->send();

            return;
        }

        if ($this->paymentAmount < $this->totalPrice) {
            Notification::make()
                ->title('支払い金額が不足しています。')
                ->danger()
                ->send();

            return;
        }

        try {
            // トランザクション内で在庫チェックと注文処理を安全に行う
            DB::transaction(function () {
                foreach ($this->cart as $item) {
                    $product = Product::where('id', $item['id'])->lockForUpdate()->first();

                    if (!$product) {
                        throw new Exception('商品が存在しません。');
                    }

                    if ($product->stock < $item['quantity']) {
                        throw new Exception('注文数量が在庫を超えています: '.$product->name);
                    }

                    $product->decrement('stock', $item['quantity']);

                    Orders::create([
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'image' => $item['image'] ?? null,
                        'total_price' => $item['price'] * $item['quantity'],
                        'options' => isset($item['options']) ? json_encode($item['options']) : null,
                    ]);
                }
            });
        } catch (Throwable $e) {
            $this->showPaymentPopup = false;
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        // カートを空にしてセッションデータをクリア
        $this->cart = [];
        $this->showPaymentPopup = false;
        $this->calculateTotalPrice();
        session()->forget('cart');

        Notification::make()
            ->title('注文が確定しました！')
            ->success()
            ->send();
    }

    protected function getActions(): array
    {
        return [];
    }

    public function updatedPaymentAmount($value): void
    {
        $this->paymentAmount = $value === '' ? 0 : (int) $value;
    }
}
