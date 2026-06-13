<?php

namespace App\Http\Controllers;

use App\Models\RideHistory;
use App\Models\UserTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminStatsController extends Controller
{
    public function index(): View
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $periodLabel = $monthStart->format('d.m.Y').' – '.$monthEnd->format('d.m.Y');

        $rideHistoryInMonth = RideHistory::query()->where('created_at', '>=', $monthStart);
        $ticketsInMonth = UserTicket::query()->where('purchase_date', '>=', $monthStart);

        $searchStats = [
            'total' => (clone $rideHistoryInMonth)->count(),
            'today' => RideHistory::query()
                ->where('created_at', '>=', now()->startOfDay())
                ->where('created_at', '>=', $monthStart)
                ->count(),
            'this_week' => RideHistory::query()
                ->where('created_at', '>=', now()->startOfWeek())
                ->where('created_at', '>=', $monthStart)
                ->count(),
        ];

        $userStats = [
            'unique_users' => (int) (clone $rideHistoryInMonth)->distinct()->count('user_id'),
            'avg_duration' => round((float) ((clone $rideHistoryInMonth)->avg('duration_minutes') ?? 0), 1),
        ];

        $ticketStats = [
            'sold' => (clone $ticketsInMonth)->count(),
            'active' => (clone $ticketsInMonth)
                ->where('is_active', true)
                ->where(function (Builder $query) {
                    $query->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', now());
                })
                ->count(),
            'revenue' => round((float) ((clone $ticketsInMonth)->sum('final_price') ?? 0), 2),
        ];

        $topRoutes = DB::table('ride_history as rh')
            ->join('gtfs_trips as gt', 'gt.id', '=', 'rh.trip_id')
            ->join('gtfs_routes as gr', 'gr.id', '=', 'gt.route_id')
            ->where('rh.created_at', '>=', $monthStart)
            ->select(
                'gr.route_short_name',
                'gr.route_long_name',
                DB::raw('COUNT(*) as searches'),
            )
            ->groupBy('gr.route_short_name', 'gr.route_long_name')
            ->orderByDesc('searches')
            ->limit(5)
            ->get();

        $topPairs = DB::table('ride_history as rh')
            ->join('gtfs_stops as fs', 'fs.id', '=', 'rh.from_stop_id')
            ->join('gtfs_stops as ts', 'ts.id', '=', 'rh.to_stop_id')
            ->where('rh.created_at', '>=', $monthStart)
            ->select(
                'fs.stop_name as from_stop',
                'ts.stop_name as to_stop',
                DB::raw('COUNT(*) as searches'),
            )
            ->groupBy('fs.stop_name', 'ts.stop_name')
            ->orderByDesc('searches')
            ->limit(5)
            ->get();

        $ticketsByType = DB::table('user_tickets as ut')
            ->join('ticket_types as tt', 'tt.id', '=', 'ut.ticket_type_id')
            ->where('ut.purchase_date', '>=', $monthStart)
            ->select(
                'tt.name',
                DB::raw('COUNT(*) as sold'),
                DB::raw('COALESCE(SUM(ut.final_price), 0) as revenue'),
            )
            ->groupBy('tt.name')
            ->orderByDesc('sold')
            ->get();

        return view('admin.stats', compact(
            'periodLabel',
            'searchStats',
            'userStats',
            'ticketStats',
            'topRoutes',
            'topPairs',
            'ticketsByType',
        ));
    }
}
