<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventBannerController extends Controller
{
    /**
     * Upload / replace event banner image
     * Organizer can update own event, admin can update all.
     */
    public function upload(Request $request, Event $event)
    {
        // Authorization: organizer owns event OR admin
        if ($request->user()->id !== $event->organizer_id && ! $request->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized to upload banner for this event.',
            ], 403);
        }

        // Validation: strict image checks
        $validated = $request->validate([
            'banner_image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120', // 5MB (KB)
                'dimensions:min_width=800,min_height=600',
            ],
        ]);

        $file = $validated['banner_image'];

        // Delete old banner if exists
        if ($event->banner_image) {
            Storage::disk('public')->delete($event->banner_image);
        }

        // Safe filename (no user filename), keep extension from uploaded file
        $extension = $file->extension(); // based on file, safer than trusting original name
        $filename = 'event-' . $event->id . '-' . Str::random(16) . '.' . $extension;

        // Store under public disk (because banner is public)
        $path = $file->storeAs('events', $filename, 'public');

        $event->update([
            'banner_image' => $path,
        ]);

        return response()->json([
            'message' => 'Banner uploaded successfully.',
            'data' => $event->fresh(),
            'banner_url' => Storage::disk('public')->url($path),
        ]);
    }
}
