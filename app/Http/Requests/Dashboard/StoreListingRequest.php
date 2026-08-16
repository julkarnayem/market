<?php
namespace App\Http\Requests\Dashboard;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canSell() ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id'    => ['required','integer','exists:categories,id'],
            'title'          => ['required','string','min:10','max:255'],
            'description'    => ['required','string','min:50','max:10000'],
            'price_bdt'      => ['required','numeric','min:1','max:9999999'],
            'quantity'       => ['required','integer','min:1','max:9999'],
            'save_as_draft'  => ['nullable','boolean'],
            'policy_accept'  => ['required_unless:save_as_draft,true','accepted'],
            'images.*'       => ['nullable','file','mimes:jpg,jpeg,png,webp','max:5120'],
            'images'         => ['nullable','array','max:10'],
            'attributes'     => ['nullable','array'],
            'attributes.*'   => ['nullable','string','max:1000'],
        ];
    }
    public function messages(): array
    {
        return [
            'title.min'          => 'Title must be at least 10 characters.',
            'description.min'    => 'Description must be at least 50 characters.',
            'policy_accept.accepted' => 'You must accept the seller policy before submitting.',
        ];
    }
}
