<?php

namespace Database\Seeders;

use App\Models\TicketType;
use Illuminate\Database\Seeder;

class TicketTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => '60-minutowy',
                'price' => 4.00,
                'validity_minutes' => TicketType::VALIDITY_60_MIN,
                'is_long_term' => false,
            ],
            [
                'name' => 'Tygodniowy',
                'price' => 35.00,
                'validity_minutes' => TicketType::VALIDITY_WEEKLY,
                'is_long_term' => true,
            ],
            [
                'name' => 'Miesięczny',
                'price' => 120.00,
                'validity_minutes' => TicketType::VALIDITY_MONTHLY,
                'is_long_term' => true,
            ],
            [
                'name' => 'Semestralny',
                'price' => 550.00,
                'validity_minutes' => TicketType::VALIDITY_SEMESTER,
                'is_long_term' => true,
            ],
        ];

        foreach ($types as $type) {
            TicketType::query()->updateOrCreate(
                ['name' => $type['name']],
                $type,
            );
        }
    }
}
