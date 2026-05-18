<?php

namespace App\Http\Requests;

use App\Support\ValidationRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim string inputs before validation.
     *
     * @return array<string, mixed>
     */
    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach ($this->all() as $key => $value) {
            if (is_string($value)) {
                $trimmed[$key] = trim($value);
            }
        }

        if ($trimmed !== []) {
            $this->merge($trimmed);
        }
    }

    /**
     * Block privilege-escalation fields on public endpoints.
     *
     * @return array<string, list<string>>
     */
    protected function prohibitedPrivilegeRules(): array
    {
        $rules = [];

        foreach (ValidationRules::prohibitedPrivilegeFields() as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator);
    }
}
