<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'is_admin', 'created_at']);

        return response()->json(['data' => $users]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password'],
        ]);

        if (! empty($data['is_admin'])) {
            $user->forceFill(['is_admin' => true])->save();
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'created_at' => $user->created_at,
        ], 201);
    }
}
