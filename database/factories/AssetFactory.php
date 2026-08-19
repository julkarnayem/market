<?php
namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Enums\InventoryType;
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
            // Same shape ListingService produces: slug + "-" + 8 lowercase chars.
            'slug'               => Str::slug($title) . '-' . Str::lower(Str::random(8)),
            'description'        => fake()->paragraphs(2, true),
            'price'              => fake()->numberBetween(5000, 1000000), // 50–10000 BDT in poisha
            'inventory_type'     => InventoryType::Single,
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

    /** One unique item — the only inventory type that accepts bids. */
    public function single(): static
    {
        return $this->state(fn () => [
            'inventory_type'     => InventoryType::Single,
            'quantity'           => 1,
            'available_quantity' => 1,
        ]);
    }

    /** A finite stock that counts down as it sells. Buy Now only. */
    public function multiple(int $quantity = 100): static
    {
        return $this->state(fn () => [
            'inventory_type'     => InventoryType::Multiple,
            'quantity'           => $quantity,
            'available_quantity' => $quantity,
        ]);
    }

    /** Never runs out and never leaves Published. Buy Now only. */
    public function unlimited(): static
    {
        return $this->state(fn () => [
            'inventory_type'     => InventoryType::Unlimited,
            'quantity'           => 1,
            'available_quantity' => 1,
        ]);
    }
}
