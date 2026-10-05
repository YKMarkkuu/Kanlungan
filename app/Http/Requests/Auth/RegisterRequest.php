<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately does not accept `campus` or `name` from the client.
 * The controller sets campus = 'pampanga' and derives name from display_name
 * (or from the email local part when display_name is blank).
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * display_name is optional. A blank string from the client is turned into
     * null by the ConvertEmptyStringsToNull global middleware before validation.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'regex:/^[a-z]+[0-9]{4}pam@student\.fatima\.edu\.ph$/i'],
            'code' => ['required', 'string', 'digits:6'],
            'student_id' => ['required', 'string', 'digits:11'],
            'display_name' => ['nullable', 'string', 'min:2', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.regex' => 'The email must be a valid OLFU Pampanga student email.',
        ];
    }
}
