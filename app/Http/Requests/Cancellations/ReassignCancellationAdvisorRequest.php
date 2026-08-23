<?php

namespace App\Http\Requests\Cancellations;

use App\DTOs\Cancellations\ReassignCancellationAdvisorData;
use Illuminate\Foundation\Http\FormRequest;

final class ReassignCancellationAdvisorRequest extends FormRequest
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'assigned_advisor_user_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toData(): ReassignCancellationAdvisorData
    {
        return new ReassignCancellationAdvisorData(
            expectedVersion: (int) $this->integer('expected_version'),
            reason: trim((string) $this->string('reason')),
            assignedAdvisorUserId: (int) $this->integer('assigned_advisor_user_id'),
        );
    }
}
