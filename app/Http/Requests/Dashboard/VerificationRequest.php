<?php
namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class VerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->canTransact()
            && in_array($user->verification_status->value, ['not_submitted','rejected']);
    }

    public function rules(): array
    {
        $method = $this->input('verification_method', 'nid');

        $rules = [
            'verification_method' => 'required|in:nid,passport,dob,driving_license',
        ];

        if ($method === 'nid') {
            // NID: front + back (2 images required)
            $rules['document_front'] = 'required|file|mimes:jpg,jpeg,png|max:10240';
            $rules['document_back']  = 'required|file|mimes:jpg,jpeg,png|max:10240';
        } elseif ($method === 'dob') {
            // Date of Birth: must be 18+, 1 document
            $rules['date_of_birth'] = 'required|date|before:-18 years';
            $rules['document_front'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:10240';
        } else {
            // Passport / Driving License: 1 document
            $rules['document_front'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:10240';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'document_front.required' => 'Please upload the required document.',
            'document_back.required'  => 'Please upload the back page of your NID.',
            'date_of_birth.before'    => 'You must be at least 18 years old.',
            'date_of_birth.required'  => 'Date of birth is required.',
        ];
    }
}
