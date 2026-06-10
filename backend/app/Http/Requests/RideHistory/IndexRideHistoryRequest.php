<?php

namespace App\Http\Requests\RideHistory;

use App\Http\Requests\ApiFormRequest;
use App\Support\ValidationRules;

class IndexRideHistoryRequest extends ApiFormRequest
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
        return [
            'page' => ValidationRules::paginationPage(),
            'per_page' => ValidationRules::paginationPerPage(50),
        ];
    }

    public function perPage(): int
    {
        return min((int) $this->input('per_page', 15), 50);
    }
}
