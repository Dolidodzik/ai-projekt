<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $announcements = Announcement::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'published_at']);

        return response()->json([
            'data' => $announcements->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'published_at' => $announcement->published_at,
            ]),
        ]);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'published_at' => $announcement->published_at,
            ],
        ]);
    }
}
