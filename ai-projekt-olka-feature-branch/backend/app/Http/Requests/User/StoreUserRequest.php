<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiFormRequest;
use App\Support\ValidationRules;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreUserRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ValidationRules::personName(),
            'email' => ValidationRules::email(unique: true),
            'password' => ValidationRules::passwordWithoutConfirmation(),
            'is_admin' => ['sometimes', 'boolean'],
            'id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'password_hash' => ['prohibited'],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json(['message' => 'Brak uprawnien administratora.'], 403)
        );
    }
}
