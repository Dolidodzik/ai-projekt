<?php

use App\Http\Controllers\AdminDatabaseController;
use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\AdminStatsController;
use App\Http\Controllers\AdminUserController;
use App\Models\Announcement;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::redirect('/', '/login');

Route::get('/login', function () {
    if (Auth::check() && Auth::user()->is_admin) {
        return redirect('/admin_panel/');
    }

    return view('auth.login');
})->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $user = User::where('email', $credentials['email'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->getAuthPassword())) {
        return back()
            ->withErrors(['password' => 'Nieprawidlowe haslo.'])
            ->onlyInput('email');
    }

    if (! $user->is_admin) {
        return back()
            ->withErrors(['email' => 'To konto nie ma uprawnien administratora. Uzyj admin@example.com.'])
            ->onlyInput('email');
    }

    Auth::login($user);
    $request->session()->regenerate();

    return redirect('/admin_panel/');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

Route::prefix('admin_panel')->middleware('admin')->group(function () {
    Route::get('/', [AdminStatsController::class, 'index'])->name('admin.stats');
    Route::get('/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/reports/{report}', [AdminReportController::class, 'show'])->name('admin.reports.show');
    Route::post('/reports/{report}/status', [AdminReportController::class, 'updateStatus'])->name('admin.reports.status');
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
    Route::get('/users/new', [AdminUserController::class, 'create'])->name('admin.users.create');
    Route::post('/users/new', [AdminUserController::class, 'store'])->name('admin.users.store');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
    Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
    Route::post('/users/{user}/edit', [AdminUserController::class, 'update'])->name('admin.users.update');
    Route::post('/users/{user}/delete', [AdminUserController::class, 'destroy'])->name('admin.users.delete');

    Route::get('/database', [AdminDatabaseController::class, 'index'])->name('admin.database.index');
    Route::get('/database/{table}', [AdminDatabaseController::class, 'table'])->name('admin.database.table')->where('table', '[a-z][a-z0-9_]*');
    Route::get('/database/{table}/new', [AdminDatabaseController::class, 'create'])->name('admin.database.create')->where('table', '[a-z][a-z0-9_]*');
    Route::post('/database/{table}/new', [AdminDatabaseController::class, 'store'])->name('admin.database.store')->where('table', '[a-z][a-z0-9_]*');
    Route::get('/database/{table}/records/{record}', [AdminDatabaseController::class, 'show'])->name('admin.database.show')->where('table', '[a-z][a-z0-9_]*');
    Route::get('/database/{table}/records/{record}/edit', [AdminDatabaseController::class, 'edit'])->name('admin.database.edit')->where('table', '[a-z][a-z0-9_]*');
    Route::post('/database/{table}/records/{record}/edit', [AdminDatabaseController::class, 'update'])->name('admin.database.update')->where('table', '[a-z][a-z0-9_]*');
    Route::post('/database/{table}/records/{record}/delete', [AdminDatabaseController::class, 'destroy'])->name('admin.database.delete')->where('table', '[a-z][a-z0-9_]*');

    Route::get('/announcements', function () {
        $announcements = Announcement::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'published_at']);

        return view('admin.announcements.index', compact('announcements'));
    })->name('admin.announcements.index');

    Route::get('/announcements/new', function () {
        return view('admin.announcements.new');
    })->name('admin.announcements.new');

    Route::post('/announcements/new', function (Request $request) {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'images.*' => ['nullable', 'image', 'max:51200'],
        ]);

        $content = $data['content'];
        $files = $request->file('images', []);
        $uploadsPath = public_path('uploads/announcements');

        if (! is_dir($uploadsPath)) {
            mkdir($uploadsPath, 0775, true);
        }

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $uuid = (string) Str::uuid();
            $extension = $file->getClientOriginalExtension();
            $safeExtension = $extension ? '.'.strtolower($extension) : '';
            $relativePath = 'uploads/announcements/'.$uuid.$safeExtension;
            $file->move($uploadsPath, $uuid.$safeExtension);

            Image::create([
                'uuid' => $uuid,
                'file_name' => $relativePath,
            ]);

            $content .= '<p><img src="/'.$relativePath.'" alt=""></p>';
        }

        $announcement = Announcement::create([
            'title' => $data['title'],
            'content' => $content,
            'admin_id' => $request->user()->id,
        ]);

        return redirect('/admin_panel/announcements/'.$announcement->id);
    })->name('admin.announcements.store');

    Route::get('/announcements/{announcement}', function (Announcement $announcement) {
        return view('admin.announcements.show', compact('announcement'));
    })->name('admin.announcements.show');

    Route::get('/announcements/{announcement}/edit', function (Announcement $announcement) {
        return view('admin.announcements.edit', compact('announcement'));
    })->name('admin.announcements.edit');

    Route::post('/announcements/{announcement}/edit', function (Request $request, Announcement $announcement) {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'images.*' => ['nullable', 'image', 'max:51200'],
        ]);

        $content = $data['content'];
        $files = $request->file('images', []);
        $uploadsPath = public_path('uploads/announcements');

        if (! is_dir($uploadsPath)) {
            mkdir($uploadsPath, 0775, true);
        }

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $uuid = (string) Str::uuid();
            $extension = $file->getClientOriginalExtension();
            $safeExtension = $extension ? '.'.strtolower($extension) : '';
            $relativePath = 'uploads/announcements/'.$uuid.$safeExtension;
            $file->move($uploadsPath, $uuid.$safeExtension);

            Image::create([
                'uuid' => $uuid,
                'file_name' => $relativePath,
            ]);

            $content .= '<p><img src="/'.$relativePath.'" alt=""></p>';
        }

        $announcement->update([
            'title' => $data['title'],
            'content' => $content,
            'admin_id' => $request->user()->id,
        ]);

        return redirect('/admin_panel/announcements/'.$announcement->id);
    })->name('admin.announcements.update');

    Route::post('/announcements/{announcement}/delete', function (Announcement $announcement) {
        $announcement->delete();
        return redirect('/admin_panel/announcements');
    })->name('admin.announcements.delete');

    Route::get('/announcments', fn () => redirect('/admin_panel/announcements'));
    Route::get('/announcments/new', fn () => redirect('/admin_panel/announcements/new'));
    Route::get('/announcments/{announcement}', fn ($announcement) => redirect('/admin_panel/announcements/'.$announcement));
    Route::get('/announcments/{announcement}/edit', fn ($announcement) => redirect('/admin_panel/announcements/'.$announcement.'/edit'));
    Route::post('/announcments/{announcement}/delete', function ($announcement) {
        $item = Announcement::query()->findOrFail($announcement);
        $item->delete();
        return redirect('/admin_panel/announcements');
    });
});
