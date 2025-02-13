<?php

namespace Tests\Unit;

use App\Filament\Pages\OrderPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class OrderPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Session::flush();
    }

    public function test_add_to_cart_increments_quantity()
    {
        $product = Product::factory()->create([
            'stock' => 5,
            'price' => 100,
            'name'  => 'Test Product',
            'image' => 'test.jpg',
        ]);

        $orderPage = new OrderPage();
        $orderPage->mount();

        $orderPage->addToCart($product->id);
        $this->assertCount(1, $orderPage->cart);
        $this->assertEquals(1, $orderPage->cart[0]['quantity']);

        $orderPage->addToCart($product->id);
        $this->assertCount(1, $orderPage->cart);
        $this->assertEquals(2, $orderPage->cart[0]['quantity']);

        $orderPage->addToCart($product->id);
        $orderPage->addToCart($product->id);
        $orderPage->addToCart($product->id);
        $orderPage->addToCart($product->id);
        $this->assertEquals(5, $orderPage->cart[0]['quantity']);
    }

    public function test_update_quantity_updates_and_removes_item()
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 200,
            'name'  => 'Update Test Product',
            'image' => 'update.jpg',
        ]);

        $orderPage = new OrderPage();
        $orderPage->mount();

        $orderPage->cart = [
            [
                'id'      => $product->id,
                'name'    => $product->name,
                'image'   => $product->image,
                'price'   => $product->price,
                'quantity'=> 2,
            ],
        ];

        $orderPage->updateQuantity(0, 5);
        $this->assertEquals(5, $orderPage->cart[0]['quantity']);

        $orderPage->updateQuantity(0, 0);
        $this->assertCount(0, $orderPage->cart);
    }

    public function test_remove_from_cart()
    {
        $product1 = Product::factory()->create([
            'stock' => 5,
            'price' => 100,
            'name'  => 'Product 1',
            'image' => '1.jpg',
        ]);

        $product2 = Product::factory()->create([
            'stock' => 3,
            'price' => 150,
            'name'  => 'Product 2',
            'image' => '2.jpg',
        ]);

        $orderPage = new OrderPage();
        $orderPage->mount();

        $orderPage->cart = [
            [
                'id'      => $product1->id,
                'name'    => $product1->name,
                'image'   => $product1->image,
                'price'   => $product1->price,
                'quantity'=> 1,
            ],
            [
                'id'      => $product2->id,
                'name'    => $product2->name,
                'image'   => $product2->image,
                'price'   => $product2->price,
                'quantity'=> 2,
            ],
        ];

        $orderPage->removeFromCart(0);
        $this->assertCount(1, $orderPage->cart);
        $this->assertEquals($product2->id, $orderPage->cart[0]['id']);
    }

    public function test_calculate_total_price()
    {
        $product1 = Product::factory()->create([
            'stock' => 5,
            'price' => 100,
            'name'  => 'Product 1',
            'image' => '1.jpg',
        ]);

        $product2 = Product::factory()->create([
            'stock' => 3,
            'price' => 150,
            'name'  => 'Product 2',
            'image' => '2.jpg',
        ]);

        $orderPage = new OrderPage();
        $orderPage->mount();

        $orderPage->cart = [
            [
                'id'      => $product1->id,
                'name'    => $product1->name,
                'image'   => $product1->image,
                'price'   => $product1->price,
                'quantity'=> 2,
            ],
            [
                'id'      => $product2->id,
                'name'    => $product2->name,
                'image'   => $product2->image,
                'price'   => $product2->price,
                'quantity'=> 1,
            ],
        ];
        $orderPage->updateCartSession();

        $reflection = new \ReflectionClass($orderPage);
        $method = $reflection->getMethod('calculateTotalPrice');
        $method->setAccessible(true);
        $method->invoke($orderPage);

        $expectedTotal = (100 * 2) + (150 * 1);
        $this->assertEquals($expectedTotal, $orderPage->totalPrice);
    }

    public function test_confirm_order_successful()
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 100,
            'name'  => 'Order Product',
            'image' => 'order.jpg',
        ]);

        $orderPage = new OrderPage();
        $orderPage->mount();

        $orderPage->addToCart($product->id);
        $orderPage->paymentAmount = 150;

        $orderPage->confirmOrder();

        $this->assertEmpty($orderPage->cart);
        $this->assertFalse($orderPage->showPaymentPopup);

        $product->refresh();
        $this->assertEquals(9, $product->stock);

        $this->assertDatabaseHas('orders', [
            'name'        => $product->name,
            'quantity'    => 1,
            'total_price' => $product->price * 1,
        ]);
    }

    public function test_confirm_order_insufficient_payment()
    {
        $product = Product::factory()->create([
            'stock' => 10,
            'price' => 100,
            'name'  => 'Insufficient Payment Product',
            'image' => 'insufficient.jpg',
        ]);

        $orderPage = new OrderPage();
        $orderPage->mount();

        $orderPage->addToCart($product->id);
        $orderPage->paymentAmount = 50;

        $initialCart = $orderPage->cart;

        $orderPage->confirmOrder();

        $this->assertEquals($initialCart, $orderPage->cart);
        $this->assertDatabaseMissing('orders', [
            'name' => $product->name,
        ]);
    }

    public function test_confirm_order_fail_due_to_stock_insufficiency()
    {
        $product = Product::factory()->create([
            'stock' => 1,
            'price' => 100,
            'name'  => 'Low Stock Product',
            'image' => 'lowstock.jpg',
        ]);

        $orderPage = new OrderPage();
        $orderPage->mount();

        $orderPage->cart = [
            [
                'id'      => $product->id,
                'name'    => $product->name,
                'image'   => $product->image,
                'price'   => $product->price,
                'quantity'=> 2,
            ],
        ];
        $orderPage->paymentAmount = 300;

        $orderPage->confirmOrder();

        $this->assertNotEmpty($orderPage->cart);
        $product->refresh();
        $this->assertEquals(1, $product->stock);
        $this->assertDatabaseMissing('orders', [
            'name' => $product->name,
        ]);
    }
}
