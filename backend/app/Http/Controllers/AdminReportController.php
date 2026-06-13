<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['new', 'in_progress', 'resolved'])],
            'sort' => ['nullable', Rule::in([
                'status_updated_at_desc',
                'status_updated_at_asc',
                'created_at_desc',
                'created_at_asc',
            ])],
        ]);

        $sortKey = $validated['sort'] ?? 'status_updated_at_desc';
        [$sort, $direction] = match ($sortKey) {
            'status_updated_at_asc' => ['status_updated_at', 'asc'],
            'created_at_desc' => ['created_at', 'desc'],
            'created_at_asc' => ['created_at', 'asc'],
            default => ['status_updated_at', 'desc'],
        };

        $reports = Report::query()
            ->with('user:id,name,email')
            ->when(
                filled($validated['q'] ?? null),
                function ($query) use ($validated) {
                    $term = '%'.$validated['q'].'%';
                    $query->where(function ($inner) use ($term) {
                        $inner->where('title', 'ilike', $term)
                            ->orWhere('description', 'ilike', $term);
                    });
                }
            )
            ->when(
                filled($validated['status'] ?? null),
                fn ($query) => $query->where('status', $validated['status'])
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->get();

        return view('admin.reports.index', [
            'reports' => $reports,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
                'sort' => $sortKey,
            ],
        ]);
    }

    public function show(Report $report): View
    {
        $report->load(['user:id,name,email', 'images']);

        return view('admin.reports.show', compact('report'));
    }

    public function updateStatus(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_progress', 'resolved'])],
        ]);

        $now = now();
        $attributes = [
            'status' => $data['status'],
            'status_updated_at' => $now,
        ];

        if ($data['status'] === 'resolved') {
            $attributes['resolved_at'] = $now;
            $attributes['resolved_by_admin_id'] = $request->user()->id;
        } else {
            $attributes['resolved_at'] = null;
            $attributes['resolved_by_admin_id'] = null;
        }

        $report->update($attributes);

        $redirectTo = $request->input('redirect_to', route('admin.reports.index'));

        return redirect($redirectTo)->with('status', 'Status zgłoszenia został zaktualizowany.');
    }
}
