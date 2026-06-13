<?php

namespace App\Http\Controllers;

use App\Models\RideHistory;
use App\Models\UserTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminStatsController extends Controller
{
    public function index(): View
    {
        $searchStats = [
            'total' => RideHistory::query()->count(),
            'today' => RideHistory::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'this_week' => RideHistory::query()->where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => RideHistory::query()->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        $userStats = [
            'unique_users' => (int) RideHistory::query()->distinct()->count('user_id'),
            'avg_duration' => round((float) (RideHistory::query()->avg('duration_minutes') ?? 0), 1),
        ];

        $ticketStats = [
            'total' => UserTicket::query()->count(),
            'active' => UserTicket::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', now());
                })
                ->count(),
            'revenue_total' => round((float) (UserTicket::query()->sum('final_price') ?? 0), 2),
            'sold_this_month' => UserTicket::query()
                ->where('purchase_date', '>=', now()->startOfMonth())
                ->count(),
            'revenue_this_month' => round((float) (UserTicket::query()
                ->where('purchase_date', '>=', now()->startOfMonth())
                ->sum('final_price') ?? 0), 2),
        ];

        $topRoutes = DB::table('ride_history as rh')
            ->join('gtfs_trips as gt', 'gt.id', '=', 'rh.trip_id')
            ->join('gtfs_routes as gr', 'gr.id', '=', 'gt.route_id')
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
            ->select(
                'tt.name',
                DB::raw('COUNT(*) as sold'),
                DB::raw('COALESCE(SUM(ut.final_price), 0) as revenue'),
            )
            ->groupBy('tt.name')
            ->orderByDesc('sold')
            ->get();

        return view('admin.stats', compact(
            'searchStats',
            'userStats',
            'ticketStats',
            'topRoutes',
            'topPairs',
            'ticketsByType',
        ));
    }
}
