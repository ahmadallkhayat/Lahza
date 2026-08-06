<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalised before validation so "  Admin " and "ADMIN@x.com" do not
     * become a second account that only differs by case or whitespace.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => trim((string) $this->input('username')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->user()->id;

        return [
            'name' => ['required', 'string', 'max:255'],

            // alpha_dash keeps the username URL- and shell-safe, and matches
            // what UserFactory generates. It is the sign-in credential, so
            // uniqueness is enforced against every other account.
            'username' => [
                'required', 'string', 'min:3', 'max:255', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($id),
            ],

            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.alpha_dash' => 'The username may only contain letters, numbers, dashes and underscores.',
            'username.unique' => 'That username is already taken.',
        ];
    }
}
