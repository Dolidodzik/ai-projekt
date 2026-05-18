<?php

namespace App\Http\Requests\RideHistory;

use App\Http\Requests\ApiFormRequest;
use App\Support\ValidationRules;

class StoreRideHistoryRequest extends ApiFormRequest
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
            'trip_id' => ValidationRules::positiveId('gtfs_trips'),
            'from_stop_id' => ValidationRules::positiveId('gtfs_stops'),
            'to_stop_id' => ValidationRules::positiveId('gtfs_stops'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->integer('from_stop_id') === $this->integer('to_stop_id')) {
                $validator->errors()->add('to_stop_id', 'Przystanek docelowy musi byc inny niz poczatkowy.');
            }
        });
    }
}
