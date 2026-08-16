<?php
namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);
        return [
            'user_id'            => User::factory()->verified(),
            'category_id'        => fn () => Category::first()?->id ?? Category::factory(),
            'title'              => $title,
            'slug'               => Str::slug($title) . '-' . fake()->unique()->randomNumber(4),
            'description'        => fake()->paragraphs(2, true),
            'price'              => fake()->numberBetween(5000, 1000000), // 50–10000 BDT in poisha
            'quantity'           => 1,
            'available_quantity' => 1,
            'sold_quantity'      => 0,
            'status'             => AssetStatus::Published,
            'views_count'        => 0,
        ];
    }

    public function seller(): static
    {
        return $this->for(User::factory()->verified(), 'seller');
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::Published]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::Draft]);
    }
}
