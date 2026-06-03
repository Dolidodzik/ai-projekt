<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MinimalTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $userEmail = (string) config('seed.user.email');
        $userId = DB::table('users')->where('email', $userEmail)->value('id');

        if (! $userId) {
            return;
        }

        $this->seedUserTickets((int) $userId);
        $this->seedUserReportWithImages((int) $userId);
        $this->seedRideHistory((int) $userId);
    }

    protected function seedUserTickets(int $userId): void
    {
        $ticketTypes = DB::table('ticket_types')->pluck('id', 'name');
        $now = now();

        $monthlyId = $ticketTypes['Monthly'] ?? null;
        if ($monthlyId !== null) {
            $exists = DB::table('user_tickets')
                ->where('user_id', $userId)
                ->where('ticket_type_id', $monthlyId)
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                DB::table('user_tickets')->insert([
                    'user_id' => $userId,
                    'ticket_type_id' => $monthlyId,
                    'purchase_date' => $now,
                    'valid_from' => $now->copy()->subDays(2),
                    'valid_until' => $now->copy()->addDays(28),
                    'is_active' => true,
                ]);
            }
        }

        $minutes60Id = $ticketTypes['60 minutes'] ?? null;
        if ($minutes60Id !== null) {
            $exists = DB::table('user_tickets')
                ->where('user_id', $userId)
                ->where('ticket_type_id', $minutes60Id)
                ->where('is_active', false)
                ->exists();

            if (! $exists) {
                DB::table('user_tickets')->insert([
                    'user_id' => $userId,
                    'ticket_type_id' => $minutes60Id,
                    'purchase_date' => $now,
                    'valid_from' => null,
                    'valid_until' => null,
                    'is_active' => false,
                ]);
            }
        }
    }

    protected function seedUserReportWithImages(int $userId): void
    {
        DB::table('reports')->updateOrInsert(
            ['user_id' => $userId, 'title' => 'test report'],
            [
                'description' => 'testowy report',
                'status' => 'new',
                'created_at' => now(),
                'resolved_by_admin_id' => null,
                'resolved_at' => null,
            ]
        );

        $reportId = DB::table('reports')
            ->where('user_id', $userId)
            ->where('title', 'test report')
            ->value('id');

        if (! $reportId) {
            return;
        }

        $uuids = [
            '11111111-1111-1111-1111-111111111111' => 'seed-report-1.jpg',
            '22222222-2222-2222-2222-222222222222' => 'seed-report-2.jpg',
        ];

        foreach ($uuids as $uuid => $fileName) {
            DB::table('images')->updateOrInsert(
                ['uuid' => $uuid],
                ['file_name' => $fileName]
            );

            $imageId = DB::table('images')->where('uuid', $uuid)->value('id');
            if (! $imageId) {
                continue;
            }

            $pivotExists = DB::table('report_images')
                ->where('report_id', $reportId)
                ->where('image_id', $imageId)
                ->exists();

            if (! $pivotExists) {
                DB::table('report_images')->insert([
                    'report_id' => $reportId,
                    'image_id' => $imageId,
                ]);
            }
        }
    }

    protected function seedRideHistory(int $userId): void
    {
        $tripId = DB::table('gtfs_stop_times')->orderBy('trip_id')->value('trip_id');
        if (! $tripId) {
            return;
        }

        $stops = DB::table('gtfs_stop_times')
            ->where('trip_id', $tripId)
            ->orderBy('stop_sequence')
            ->pluck('stop_id')
            ->values();

        if ($stops->count() < 2) {
            return;
        }

        $fromStopId = (int) $stops->first();
        $toStopId = (int) $stops->last();

        $exists = DB::table('ride_history')
            ->where('user_id', $userId)
            ->where('trip_id', $tripId)
            ->where('from_stop_id', $fromStopId)
            ->where('to_stop_id', $toStopId)
            ->exists();

        if (! $exists) {
            $fromTime = DB::table('gtfs_stop_times')
                ->where('trip_id', $tripId)
                ->where('stop_id', $fromStopId)
                ->value('departure_time');
            $toTime = DB::table('gtfs_stop_times')
                ->where('trip_id', $tripId)
                ->where('stop_id', $toStopId)
                ->value('arrival_time');

            $durationMinutes = 25;
            if (is_string($fromTime) && is_string($toTime)) {
                $fromParts = array_map('intval', explode(':', $fromTime));
                $toParts = array_map('intval', explode(':', $toTime));
                $fromSeconds = ($fromParts[0] * 3600) + ($fromParts[1] * 60) + ($fromParts[2] ?? 0);
                $toSeconds = ($toParts[0] * 3600) + ($toParts[1] * 60) + ($toParts[2] ?? 0);
                if ($toSeconds < $fromSeconds) {
                    $toSeconds += 86400;
                }
                $durationMinutes = max(1, (int) round(($toSeconds - $fromSeconds) / 60));
            }

            DB::table('ride_history')->insert([
                'user_id' => $userId,
                'trip_id' => $tripId,
                'from_stop_id' => $fromStopId,
                'to_stop_id' => $toStopId,
                'duration_minutes' => $durationMinutes,
            ]);
        }
    }
}
