<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Support\ValidationRules;

class LoginRequest extends ApiFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_merge($this->prohibitedPrivilegeRules(), [
            'email' => ValidationRules::email(),
            'password' => ValidationRules::currentPassword(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Podaj poprawny adres email (wymagany znak @).',
            'email.required' => 'Email jest wymagany.',
            'password.required' => 'Haslo jest wymagane.',
        ];
    }
}
