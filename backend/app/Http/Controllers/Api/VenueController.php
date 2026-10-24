<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    /**
     * Get all active venues
     */
    public function index()
    {
        $venues = Venue::where('is_active', true)
            ->withCount('events')
            ->orderBy('city')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $venues,
        ]);
    }

    /**
     * Get single venue with events
     */
    public function show(Venue $venue)
    {
        $venue->load(['events' => function ($query) {
            $query->published()->upcoming()->with(['category', 'organizer']);
        }]);

        return response()->json([
            'data' => $venue,
        ]);
    }

    /**
     * Create new venue (Admin only)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'address'     => 'required|string',
            'city'        => 'required|string|max:255',
            'province'    => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'capacity'    => 'nullable|integer|min:1',
            'facilities'  => 'nullable|array',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'is_active'   => 'boolean',
        ]);

        $venue = Venue::create([
            'name'        => $validated['name'],
            'address'     => $validated['address'],
            'city'        => $validated['city'],
            'province'    => $validated['province'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'capacity'    => $validated['capacity'] ?? null,
            'facilities'  => $validated['facilities'] ?? null,
            'latitude'    => $validated['latitude'] ?? null,
            'longitude'   => $validated['longitude'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Venue created successfully',
            'data'    => $venue,
        ], 201);
    }

    /**
     * Update venue (Admin only)
     */
    public function update(Request $request, Venue $venue)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'address'     => 'sometimes|string',
            'city'        => 'sometimes|string|max:255',
            'capacity'    => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $venue->update($validated);

        return response()->json([
            'message' => 'Venue updated successfully',
            'data'    => $venue,
        ]);
    }

    /**
     * Delete venue (Admin only)
     */
    public function destroy(Venue $venue)
    {
        $venue->delete();

        return response()->json([
            'message' => 'Venue deleted successfully',
        ]);
    }
}
