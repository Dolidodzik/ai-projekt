<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;
use App\Support\ValidationRules;

class UpdateProfileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_merge($this->prohibitedPrivilegeRules(), [
            'name' => ValidationRules::personName(),
            'email' => ValidationRules::email(
                unique: true,
                ignoreUserId: $this->user()?->id,
            ),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Podaj poprawny adres email (wymagany znak @).',
            'email.unique' => 'Ten adres email jest juz zajety.',
            'name.regex' => 'Imie moze zawierac tylko litery, cyfry i podstawowe znaki.',
        ];
    }
}
