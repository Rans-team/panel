<?php

use App\Filament\Pages\OrderPage;
use App\Models\Product;
use App\Models\Orders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('adds in-stock items to cart', function () {
    $product = Product::factory()->create([
        'stock' => 10,
        'price' => 1000,
        'name'  => 'Test Product',
        'image' => 'test.jpg',
    ]);

    $component = Livewire::test(OrderPage::class);
    $component->call('addToCart', $product->id);
    $cart = $component->get('cart');

    expect($cart)->toHaveCount(1);
    expect($cart[0]['id'])->toBe($product->id);
    expect($cart[0]['quantity'])->toBe(1);
});

it('increments quantity for duplicate items', function () {
    $product = Product::factory()->create([
        'stock' => 10,
        'price' => 1000,
        'name'  => 'Test Product',
        'image' => 'test.jpg',
    ]);

    $component = Livewire::test(OrderPage::class);
    $component->call('addToCart', $product->id);
    $component->call('addToCart', $product->id);
    $cart = $component->get('cart');

    expect($cart)->toHaveCount(1);
    expect($cart[0]['quantity'])->toBe(2);
});

it('limits quantity to available stock', function () {
    $product = Product::factory()->create([
        'stock' => 2,
        'price' => 1000,
        'name'  => 'Limited Stock Product',
        'image' => 'limited.jpg',
    ]);

    $component = Livewire::test(OrderPage::class);
    $component->call('addToCart', $product->id);
    $component->call('addToCart', $product->id);
    $component->call('addToCart', $product->id);
    $cart = $component->get('cart');

    expect($cart)->toHaveCount(1);
    expect($cart[0]['quantity'])->toBe(2);
});

it('updates quantity correctly', function () {
    $product = Product::factory()->create([
        'stock' => 10,
        'price' => 1000,
        'name'  => 'Test Product',
        'image' => 'test.jpg',
    ]);

    $component = Livewire::test(OrderPage::class);
    $component->call('addToCart', $product->id);
    $component->call('updateQuantity', 0, 5);
    $cart = $component->get('cart');

    expect($cart[0]['quantity'])->toBe(5);
});

it('removes item when quantity is zero', function () {
    $product = Product::factory()->create([
        'stock' => 10,
        'price' => 1000,
        'name'  => 'Test Product',
        'image' => 'test.jpg',
    ]);

    $component = Livewire::test(OrderPage::class);
    $component->call('addToCart', $product->id);
    $component->call('updateQuantity', 0, 0);
    $cart = $component->get('cart');

    expect($cart)->toBeEmpty();
});

it('You can remove products using the removeFromCart method.', function () {
    $product = Product::factory()->create([
        'stock' => 10,
        'price' => 1000,
        'name'  => 'Test Product',
        'image' => 'test.jpg',
    ]);

    $component = Livewire::test(OrderPage::class);
    $component->call('addToCart', $product->id);
    $component->call('removeFromCart', 0);
    $cart = $component->get('cart');

    expect($cart)->toBeEmpty();
});

it('confirms order successfully', function () {
    $product = Product::factory()->create([
        'stock' => 5,
        'price' => 1000,
        'name'  => 'Order Product',
        'image' => 'order.jpg',
    ]);

    $component = Livewire::test(OrderPage::class);
    $component->call('addToCart', $product->id);
    $component->set('paymentAmount', 1000);
    $component->call('confirmOrder');
    
    $cart = $component->get('cart');
    expect($cart)->toBeEmpty();
    
    $product->refresh();
    expect($product->stock)->toBe(4);
    
    $order = Orders::where('name', 'Order Product')->first();
    expect($order)->not->toBeNull();
    expect($order->quantity)->toBe(1);
});
