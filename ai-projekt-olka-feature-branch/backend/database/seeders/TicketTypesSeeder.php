<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketTypesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Monthly', 'price' => 150.00, 'validity_minutes' => null, 'is_long_term' => true],
            ['name' => 'Semester', 'price' => 450.00, 'validity_minutes' => null, 'is_long_term' => true],
            ['name' => '60 minutes', 'price' => 4.00, 'validity_minutes' => 60, 'is_long_term' => false],
            ['name' => 'Weekly', 'price' => 35.00, 'validity_minutes' => null, 'is_long_term' => true],
        ];

        foreach ($rows as $r) {
            DB::table('ticket_types')->updateOrInsert(
                ['name' => $r['name']],
                [
                    'price' => $r['price'],
                    'validity_minutes' => $r['validity_minutes'],
                    'is_long_term' => $r['is_long_term'],
                ]
            );
        }
    }
}
