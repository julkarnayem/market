<?php
namespace Tests\Concerns;

use App\Enums\InventoryType;
use App\Models\Asset;
use App\Models\Category;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Money;

/**
 * Fixtures for the bidding / custom-offer suites.
 *
 * Category has no factory, and AssetFactory falls back to Category::first(),
 * so every test that builds a listing has to put one row in the table first.
 */
trait BuildsMarketplace
{
    protected function category(): Category
    {
        return Category::first() ?? Category::create([
            'name'      => 'Social Media',
            'slug'      => 'social-media',
            'is_active' => true,
            'position'  => 1,
        ]);
    }

    /** A seller who can actually sell: active, verified, has a wallet. */
    protected function seller(): User
    {
        $seller = User::factory()->verified()->create();
        $this->walletFor($seller);

        return $seller;
    }

    /** A buyer only needs to be able to transact — buying is not verification-gated. */
    protected function buyer(): User
    {
        return User::factory()->create();
    }

    /**
     * Wallet::factory() does not exist — the model imports HasFactory without
     * applying it (see .github/known-test-failures.txt), so build the row.
     */
    protected function walletFor(User $user): Wallet
    {
        return Wallet::create([
            'user_id'           => $user->id,
            'available_balance' => 0,
            'pending_balance'   => 0,
            'currency'          => 'BDT',
        ]);
    }

    /**
     * A published listing owned by $seller.
     *
     * @param int $priceBdt price in taka, converted to poisha like the app does
     */
    protected function listing(
        User $seller,
        InventoryType $type = InventoryType::Single,
        int $priceBdt = 5000,
        int $quantity = 1,
    ): Asset {
        $this->category();

        $state = match ($type) {
            InventoryType::Single    => ['inventory_type' => $type, 'quantity' => 1, 'available_quantity' => 1],
            InventoryType::Unlimited => ['inventory_type' => $type, 'quantity' => 1, 'available_quantity' => 1],
            InventoryType::Multiple  => ['inventory_type' => $type, 'quantity' => $quantity, 'available_quantity' => $quantity],
        };

        return Asset::factory()->published()->create($state + [
            'user_id' => $seller->id,
            'price'   => Money::toPoisha($priceBdt),
        ]);
    }
}
