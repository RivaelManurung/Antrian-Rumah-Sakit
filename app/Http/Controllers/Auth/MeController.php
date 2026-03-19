<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $roles = method_exists($user, 'roles')
            ? $user->roles()->pluck('name')->values()->all()
            : [];

        return response()->json([
            'data' => [
                'user' => $user,
                'roles' => $roles,
            ],
        ]);
    }
}
