<?php

namespace App\Http\Requests\Advisor;

use App\Enums\CancellationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImportResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([CancellationType::MOTO->value, CancellationType::CREDIT->value])],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:'.$this->maxKilobytes()],
        ];
    }

    public function cancellationType(): CancellationType
    {
        return CancellationType::from((string) $this->validated('type'));
    }

    private function maxKilobytes(): int
    {
        return (int) ceil(((int) config('imports.max_bytes')) / 1024);
    }
}
