<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Exports\OrganizerOrdersExport;
use Maatwebsite\Excel\Facades\Excel;

class OrganizerOrderController extends Controller
{
    public function index(Request $request)
    {
        $organizerId = $request->user()->id;
        
        $query = Order::with(['event', 'user'])
            ->whereHas('event', function($q) use ($organizerId) {
                $q->where('organizer_id', $organizerId);
            });

        if ($request->has('event_id') && $request->event_id !== 'all') {
            $query->where('event_id', $request->event_id);
        }

        // Pagination 15 data per halaman
        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($orders);
    }

    public function export(Request $request)
    {
        $organizerId = $request->user()->id;
        $eventId = $request->event_id === 'all' ? null : $request->event_id;

        return Excel::download(new OrganizerOrdersExport($organizerId, $eventId), 'Transaksi_Event.xlsx');
    }
}