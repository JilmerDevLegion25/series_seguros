<?php

namespace App\Http\Requests\Moto;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteMotoCancellationRequest extends FormRequest
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
            'challenge_reference' => ['required', 'string', 'size:64'],
            'otp' => ['required', 'string', 'digits:6'],
        ];
    }
}
