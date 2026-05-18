<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Support\ValidationRules;

class RegisterRequest extends ApiFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_merge($this->prohibitedPrivilegeRules(), [
            'name' => ValidationRules::personName(),
            'email' => ValidationRules::email(unique: true),
            'password' => ValidationRules::password(),
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
            'password.min' => 'Haslo musi miec co najmniej 8 znakow.',
            'password.confirmed' => 'Potwierdzenie hasla musi byc zgodne.',
        ];
    }
}
