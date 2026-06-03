<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class ValidationRules
{
    public const PERSON_NAME_REGEX = '/^[\p{L}\p{M}\p{N}\s\-\.\']+$/u';

    public const TITLE_REGEX = '/^[\p{L}\p{M}\p{N}\s\-\.\,\:\;\!\?\'\"\(\)\/]+$/u';

    /**
     * @return list<string|Password>
     */
    public static function email(bool $unique = false, ?int $ignoreUserId = null): array
    {
        $rules = [
            'required',
            'string',
            'email:filter,rfc',
            'max:255',
            'lowercase',
        ];

        if ($unique) {
            $rule = 'unique:users,email';
            if ($ignoreUserId !== null) {
                $rule .= ','.$ignoreUserId;
            }
            $rules[] = $rule;
        }

        return $rules;
    }

    /**
     * @return list<string|Password>
     */
    public static function personName(): array
    {
        return [
            'required',
            'string',
            'min:2',
            'max:255',
            'regex:'.self::PERSON_NAME_REGEX,
        ];
    }

    /**
     * @return list<string|Password>
     */
    public static function password(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::min(8)->max(72),
        ];
    }

    /**
     * @return list<string|Password>
     */
    public static function passwordWithoutConfirmation(): array
    {
        return [
            'required',
            'string',
            Password::min(8)->max(72),
        ];
    }

    /**
     * @return list<string>
     */
    public static function currentPassword(): array
    {
        return ['required', 'string', 'max:72'];
    }

    /**
     * @return list<string>
     */
    public static function title(): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:255',
            'regex:'.self::TITLE_REGEX,
        ];
    }

    /**
     * @return list<string>
     */
    public static function description(): array
    {
        return [
            'required',
            'string',
            'min:10',
            'max:5000',
        ];
    }

    /**
     * @return list<string>
     */
    public static function positiveId(string $table): array
    {
        return ['required', 'integer', 'min:1', 'exists:'.$table.',id'];
    }

    /**
     * @return list<string>
     */
    public static function paginationPage(): array
    {
        return ['sometimes', 'integer', 'min:1', 'max:10000'];
    }

    /**
     * @return list<string>
     */
    public static function paginationPerPage(int $max = 50): array
    {
        return ['sometimes', 'integer', 'min:1', 'max:'.$max];
    }

    /**
     * @return list<string>
     */
    public static function prohibitedPrivilegeFields(): array
    {
        return [
            'is_admin',
            'id',
            'user_id',
            'password_hash',
            'created_at',
            'updated_at',
            'resolved_at',
            'resolved_by_admin_id',
            'status',
        ];
    }
}
