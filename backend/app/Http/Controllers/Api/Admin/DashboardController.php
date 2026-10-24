<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function index()
    {
        $stats = [
            // Events statistics
            'events' => [
                'total' => Event::count(),
                'published' => Event::where('status', 'published')->count(),
                'pending' => Event::where('status', 'pending_approval')->count(),
                'draft' => Event::where('status', 'draft')->count(),
            ],
            
            // Orders statistics
            'orders' => [
                'total' => Order::count(),
                'confirmed' => Order::where('status', 'confirmed')->count(),
                'pending' => Order::where('status', 'pending_payment')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
            ],
            
            // Revenue statistics
            'revenue' => [
                'total' => Order::where('payment_status', 'paid')->sum('total'),
                'today' => Order::where('payment_status', 'paid')
                    ->whereDate('paid_at', today())
                    ->sum('total'),
                'this_month' => Order::where('payment_status', 'paid')
                    ->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year)
                    ->sum('total'),
                'this_year' => Order::where('payment_status', 'paid')
                    ->whereYear('paid_at', now()->year)
                    ->sum('total'),
            ],
            
            // Users statistics
            'users' => [
                'total' => User::count(),
                'organizers' => User::role('organizer')->count(),
                'admins' => User::role('admin')->count(),
                'new_today' => User::whereDate('created_at', today())->count(),
            ],
        ];

        return response()->json($stats);
    }

    /**
     * Get recent orders
     */
    public function recentOrders()
    {
        $orders = Order::with(['user', 'event'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($orders);
    }

    /**
     * Get top selling events
     */
    public function topEvents(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $events = Event::withCount([
                'tickets as total_tickets',
                'orders as total_orders'
            ])
            ->withSum('orders as total_revenue', 'total')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($events);
    }

    /**
     * Get revenue chart data
     */
    public function revenueChart(Request $request)
    {
        $period = $request->get('period', 'week'); // week, month, year
        
        $query = Order::where('payment_status', 'paid')
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders_count')
            )
            ->groupBy('date')
            ->orderBy('date');

        if ($period === 'week') {
            $query->where('paid_at', '>=', now()->subDays(7));
        } elseif ($period === 'month') {
            $query->where('paid_at', '>=', now()->subDays(30));
        } elseif ($period === 'year') {
            $query->where('paid_at', '>=', now()->subYear());
        }

        $data = $query->get();

        return response()->json($data);
    }

    /**
     * Get payment method distribution
     */
    public function paymentMethods()
    {
        $distribution = Order::where('payment_status', 'paid')
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy('payment_method')
            ->get();

        return response()->json($distribution);
    }
}
