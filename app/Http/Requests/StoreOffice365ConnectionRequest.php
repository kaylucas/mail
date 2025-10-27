<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOffice365ConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert comma-separated scopes string to array
        if ($this->has('scopes') && is_string($this->scopes)) {
            $this->merge([
                'scopes' => array_map('trim', explode(',', $this->scopes)),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'string', 'max:255'],
            'client_id' => ['prohibited'],
            'client_secret' => ['prohibited'],
            'redirect_uri' => ['prohibited'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $scopes = $this->input('scopes');
            if (empty($scopes) && empty(config('services.office365.scopes'))) {
                $v->errors()->add('scopes', 'Scopes are required via request or OFFICE365_SCOPES config.');
            }
        });
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'The Azure AD client ID is required.',
            'client_secret.required' => 'The Azure AD client secret is required.',
            'redirect_uri.required' => 'The OAuth redirect URI is required.',
            'redirect_uri.url' => 'The redirect URI must be a valid URL.',
        ];
    }
}
