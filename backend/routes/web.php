<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin_panel/');

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

    if (! $user || ! Hash::check($credentials['password'], $user->password_hash) || ! $user->is_admin) {
        abort(403);
    }

    Auth::login($user);
    $request->session()->regenerate();

    return redirect()->intended('/admin_panel/');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('admin')->name('logout');

Route::prefix('admin_panel')->middleware('admin')->group(function () {
    Route::view('/{path?}', 'admin.panel')->where('path', '.*');
});
