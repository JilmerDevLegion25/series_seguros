<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class CompletePasswordRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'public_reference' => ['required', 'string', 'size:64'],
            'otp' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'confirmed', 'max:255'],
        ];
    }
}
