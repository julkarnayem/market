<?php
namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetEdit;
use App\Models\AssetImage;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListingService
{
    public function __construct(private readonly SettingsService $settings) {}

    /** Create a new listing (draft or pending_review). */
    public function create(User $seller, array $data, array $images, bool $isDraft = false): Asset
    {
        return DB::transaction(function () use ($seller, $data, $images, $isDraft) {
            $price = Money::toPoisha($data['price_bdt']);
            $qty   = (int) $data['quantity'];

            $asset = Asset::create([
                'user_id'          => $seller->id,
                'category_id'      => $data['category_id'],
                'title'            => $data['title'],
                'slug'             => $this->uniqueSlug($data['title']),
                'description'      => $data['description'],
                'price'            => $price,
                'quantity'         => $qty,
                'available_quantity'=> $qty,
                'status'           => $isDraft ? AssetStatus::Draft : AssetStatus::PendingReview,
                'policy_accepted_at'=> $isDraft ? null : now(),
            ]);

            // Attributes
            $this->syncAttributes($asset, $data['attributes'] ?? []);

            // Images
            $this->attachImages($asset, $images);

            return $asset;
        });
    }

    /** Submit a published asset edit (creates pending version; live listing unchanged). */
    public function submitEdit(Asset $asset, array $data, array $newImages): AssetEdit
    {
        abort_if($asset->hasActiveOrder(), 422, 'Cannot edit while an active order exists.');
        abort_if($asset->hasPendingEdit(), 422, 'An edit is already pending review.');
        // Price lock if active offer
        if ($asset->isPriceLocked() && isset($data['price_bdt'])) {
            $newPoisha = Money::toPoisha($data['price_bdt']);
            abort_if($newPoisha !== $asset->price, 422, 'Price cannot be changed while an active offer exists.');
        }

        return DB::transaction(function () use ($asset, $data, $newImages) {
            $oldValues = [
                'title'       => $asset->title,
                'description' => $asset->description,
                'price'       => $asset->price,
                'quantity'    => $asset->quantity,
                'attributes'  => $asset->attributeValues->mapWithKeys(fn($av)=>[$av->category_attribute_id=>$av->value])->all(),
            ];
            $newValues = [
                'title'       => $data['title'],
                'description' => $data['description'],
                'price'       => Money::toPoisha($data['price_bdt']),
                'quantity'    => (int)$data['quantity'],
                'attributes'  => $data['attributes'] ?? [],
                'edit_reason' => $data['edit_reason'] ?? null,
            ];

            $edit = $asset->edits()->create([
                'requested_by' => auth()->id(),
                'old_values'   => $oldValues,
                'new_values'   => $newValues,
                'status'       => 'pending_edit_approval',
            ]);

            if ($asset->status === AssetStatus::Published) {
                $asset->update(['status' => AssetStatus::PendingEditApproval]);
            }

            return $edit;
        });
    }

    /** Apply an approved edit to the live asset. */
    public function applyEdit(AssetEdit $edit, User $reviewer): void
    {
        DB::transaction(function () use ($edit, $reviewer) {
            $asset = $edit->asset;
            $nv    = $edit->new_values;

            $asset->update([
                'title'       => $nv['title'],
                'description' => $nv['description'],
                'price'       => $nv['price'],
                'quantity'    => $nv['quantity'],
                'status'      => AssetStatus::Published,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->syncAttributes($asset, $nv['attributes'] ?? []);

            $edit->update(['status'=>'approved','reviewed_by'=>$reviewer->id,'reviewed_at'=>now()]);
        });
    }

    /** Reject a pending edit. */
    public function rejectEdit(AssetEdit $edit, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($edit, $reviewer, $reason) {
            $edit->update([
                'status'      => 'rejected',
                'review_note' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            // Restore published status
            $edit->asset->update(['status' => AssetStatus::Published]);
        });
    }

    /** Attach uploaded images safely to an asset. */
    public function attachImages(Asset $asset, array $files): void
    {
        $hasCover = $asset->coverImage()->exists();
        foreach ($files as $i => $file) {
            if (!$file instanceof UploadedFile) continue;
            $path = $file->store("assets/{$asset->id}", 'public');
            AssetImage::create([
                'asset_id'      => $asset->id,
                'disk'          => 'public',
                'path'          => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 100),
                'mime_type'     => $file->getMimeType(),
                'size_bytes'    => $file->getSize(),
                'is_cover'      => !$hasCover && $i === 0,
                'sort_order'    => $asset->images()->count() + $i,
            ]);
            if (!$hasCover && $i === 0) $hasCover = true;
        }
    }

    /** Sync EAV attribute values. */
    public function syncAttributes(Asset $asset, array $attributes): void
    {
        foreach ($attributes as $attrId => $value) {
            if ($value === null || $value === '') {
                $asset->attributeValues()->where('category_attribute_id', $attrId)->delete();
                continue;
            }
            $asset->attributeValues()->updateOrCreate(
                ['category_attribute_id' => $attrId],
                ['value' => (string) $value]
            );
        }
    }

    public function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i    = 2;
        while (Asset::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public function sellerEarning(int $pricePoisha): array
    {
        $feeBp      = $this->settings->sellerFeeBp();
        $feeAmount  = Money::percentOf($pricePoisha, $feeBp);
        return [
            'price'       => $pricePoisha,
            'fee_bp'      => $feeBp,
            'fee_percent' => number_format($feeBp / 100, 2),
            'fee_amount'  => $feeAmount,
            'earning'     => $pricePoisha - $feeAmount,
        ];
    }
}
