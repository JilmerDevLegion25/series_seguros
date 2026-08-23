<?php

namespace App\Http\Requests\Advisor;

use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Models\User;
use Illuminate\Validation\Rule;

final class ExportCancellationRequest extends SearchCancellationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->isAdvisor()
            && $user->can(PermissionKey::CANCELLATIONS_EXPORT->value);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['type'] = ['required', 'string', Rule::in([
            CancellationType::MOTO->value,
            CancellationType::CREDIT->value,
        ])];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'type.required' => 'Selecciona el tipo de seguro a exportar.',
            'type.in' => 'Selecciona un tipo de seguro valido.',
        ]);
    }
}
