<?php
namespace App\Policies;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    /** Only verified, active sellers. */
    public function create(User $user): bool
    {
        return $user->canSell();
    }

    /** Must own the asset and it must be editable. */
    public function update(User $user, Asset $asset): bool
    {
        return $user->id === $asset->user_id
            && $user->canSell()
            && $asset->isEditable()
            && !$asset->hasPendingEdit()
            && !in_array($asset->status->value, ['suspended','sold_out','archived']);
    }

    /** Can pause/resume own published listing. */
    public function togglePause(User $user, Asset $asset): bool
    {
        return $user->id === $asset->user_id
            && in_array($asset->status->value, ['published','paused'])
            && !$asset->hasActiveOrder();
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->id === $asset->user_id
            && in_array($asset->status->value, ['draft','rejected'])
            && !$asset->hasActiveOrder();
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->id === $asset->user_id || $asset->status === AssetStatus::Published;
    }
}
