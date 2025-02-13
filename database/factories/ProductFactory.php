<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'stock' => $this->faker->numberBetween(1, 100),
            'price' => $this->faker->numberBetween(500, 2000),
            'name'  => $this->faker->word,
            'image' => $this->faker->imageUrl,
        ];
    }
}
