<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Get all orders (with filters)
     */
public function index(Request $request)
{
    $query = Order::with([
        'user:id,name,email',   // hanya kolom yang dibutuhkan
        'event:id,title',       // hanya kolom yang dibutuhkan
        // 'tickets' dihapus dari list — tidak dibutuhkan di list view
    ])->latest();

    // Filter by status
    if ($request->filled('status') && $request->status !== 'all') {
        $query->where('status', $request->status);
    }

    // Search
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('order_code', 'like', "%{$search}%")
              ->orWhereHas('user', fn($q) =>
                  $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
              )
              ->orWhereHas('event', fn($q) =>
                  $q->where('title', 'like', "%{$search}%")
              );
        });
    }

    $orders = $query->paginate(15)->through(fn($order) => [
        'id'             => $order->id,
        'order_code'     => $order->order_code,
        'user'           => $order->user,
        'event'          => $order->event,
        'total_amount'   => $order->total_amount,
        'status'         => $order->status,
        'payment_method' => $order->payment_method,
        'created_at'     => $order->created_at,
    ]);

    return response()->json($orders);
}


    /**
     * Get single order details
     */
    public function show(Order $order)
    {
        $order->load(['user', 'event', 'tickets']);
        return response()->json($order);
    }

    /**
     * Cancel order (admin)
     */
    public function cancel(Request $request, Order $order)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($order->status === 'cancelled') {
            return response()->json([
                'message' => 'Order already cancelled',
            ], 400);
        }

        $order->update([
            'status' => 'cancelled',
            'payment_status' => 'expired',
            'cancellation_reason' => $request->reason,
        ]);

        // TODO: Process refund if paid
        // TODO: Notify user

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $order,
        ]);
    }
}
