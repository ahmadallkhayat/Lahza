<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class PasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Proves the person at the keyboard is the account holder and not
            // someone who walked up to an unlocked session.
            'current_password' => ['required', 'string', 'current_password'],

            'password' => [
                'required', 'string', 'confirmed', 'different:current_password',
                Password::defaults(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'That is not your current password.',
            'password.different' => 'The new password must be different from the current one.',
            'password.confirmed' => 'The confirmation does not match the new password.',
        ];
    }
}
