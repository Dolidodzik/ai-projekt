<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;
use App\Support\ValidationRules;

class UpdatePasswordRequest extends ApiFormRequest
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
            'current_password' => ValidationRules::currentPassword(),
            'password' => ValidationRules::password(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.min' => 'Nowe haslo musi miec co najmniej 8 znakow.',
            'password.confirmed' => 'Potwierdzenie hasla musi byc zgodne.',
        ];
    }
}
