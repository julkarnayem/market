<?php
namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('listing');
        return $this->user()?->can('update', $asset) ?? false;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required','string','min:10','max:255'],
            'description' => ['required','string','min:50','max:10000'],
            'price_bdt'   => ['required','numeric','min:1','max:9999999'],
            // No inventory_type here: the type is fixed at creation, so an edit
            // cannot turn a Single item into an Unlimited one. Quantity only
            // matters for Multiple, and ListingService ignores it otherwise.
            'quantity'    => ['nullable','integer','min:1','max:9999'],
            'attributes'  => ['nullable','array'],
            'attributes.*'=> ['nullable','string','max:1000'],
            'edit_reason' => ['nullable','string','max:500'],
        ];
    }
}
