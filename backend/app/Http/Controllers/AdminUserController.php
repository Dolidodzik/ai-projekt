<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['admin', 'user'])],
            'sort' => ['nullable', Rule::in([
                'created_at_desc',
                'created_at_asc',
                'name_asc',
                'name_desc',
                'email_asc',
                'email_desc',
            ])],
            'page' => ValidationRules::paginationPage(),
            'per_page' => ValidationRules::paginationPerPage(50),
        ]);

        $sortKey = $validated['sort'] ?? 'created_at_desc';
        [$sort, $direction] = match ($sortKey) {
            'created_at_asc' => ['created_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'email_asc' => ['email', 'asc'],
            'email_desc' => ['email', 'desc'],
            default => ['created_at', 'desc'],
        };

        $perPage = min((int) ($validated['per_page'] ?? 15), 50);

        $users = User::query()
            ->when(
                filled($validated['q'] ?? null),
                function ($query) use ($validated) {
                    $term = '%'.$validated['q'].'%';
                    $query->where(function ($inner) use ($term) {
                        $inner->where('name', 'ilike', $term)
                            ->orWhere('email', 'ilike', $term);
                    });
                }
            )
            ->when(
                ($validated['role'] ?? null) === 'admin',
                fn ($query) => $query->where('is_admin', true)
            )
            ->when(
                ($validated['role'] ?? null) === 'user',
                fn ($query) => $query->where('is_admin', false)
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'role' => $validated['role'] ?? '',
                'sort' => $sortKey,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.new');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ValidationRules::personName(),
            'email' => ValidationRules::email(unique: true),
            'password' => ValidationRules::passwordWithoutConfirmation(),
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password'],
        ]);

        if (! empty($data['is_admin'])) {
            $user->forceFill(['is_admin' => true])->save();
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Użytkownik został utworzony.');
    }

    public function show(User $user): View
    {
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ValidationRules::personName(),
            'email' => ValidationRules::email(unique: true, ignoreUserId: $user->id),
            'password' => ['nullable', ...array_slice(ValidationRules::passwordWithoutConfirmation(), 1)],
            'is_admin' => ['sometimes', 'boolean'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (filled($data['password'] ?? null)) {
            $user->update(['password_hash' => $data['password']]);
        }

        $user->forceFill(['is_admin' => ! empty($data['is_admin'])])->save();

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Użytkownik został zaktualizowany.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['user' => 'Nie możesz usunąć własnego konta.']);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Użytkownik został usunięty.');
    }
}
