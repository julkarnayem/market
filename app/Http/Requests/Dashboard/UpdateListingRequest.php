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
            'quantity'    => ['required','integer','min:1','max:9999'],
            'attributes'  => ['nullable','array'],
            'attributes.*'=> ['nullable','string','max:1000'],
            'edit_reason' => ['nullable','string','max:500'],
        ];
    }
}
