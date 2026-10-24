<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List semua user dengan role & status
     */
    public function index(Request $request)
    {
        $query = User::with('roles')
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            })
            ->when($request->role, function ($q) use ($request) {
                $q->whereHas('roles', fn($r) => $r->where('name', $request->role));
            })
            ->latest()
            ->paginate(20);

        return response()->json($query);
    }

    /**
     * Upgrade user biasa menjadi organizer
     */
    public function upgradeToOrganizer(User $user)
    {
        if ($user->hasRole('organizer')) {
            return response()->json(['message' => 'User ini sudah menjadi organizer.'], 400);
        }

        if ($user->hasRole('admin')) {
            return response()->json(['message' => 'Tidak bisa mengubah role admin.'], 403);
        }

        $user->removeRole('user');
        $user->assignRole('organizer');

        return response()->json(['message' => "{$user->name} berhasil diupgrade menjadi organizer."]);
    }
}
