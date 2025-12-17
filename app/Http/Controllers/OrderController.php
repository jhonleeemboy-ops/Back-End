<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OrderController extends Controller
{
    // List all orders with nested relationships
    public function index()
    {
        $orders = Order::select('id','order_number','customer_name','order_type','status','total_amount','created_at')
            ->with([
                'items:id,order_id,menu_item_id,quantity,subtotal,size,add_ons',
                'items.menuItem:id,name,price,medium_price,large_price'
            ])
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['orders' => $orders], 200);
    }

    // Store a new order with items
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name'        => 'required|string',
            'order_type'           => 'required|in:dine-in,takeout',
            'items'                => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.size'         => 'nullable|in:regular,medium,large',
            'items.*.add_ons'      => 'nullable|array',
            'items.*.add_ons.*.description' => 'required_with:items.*.add_ons|string',
            'items.*.add_ons.*.amount' => 'required_with:items.*.add_ons|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $ids = collect($validated['items'])->pluck('menu_item_id');
            $menuItems = MenuItem::whereIn('id', $ids)->get()->keyBy('id');

            $totalAmount = collect($validated['items'])->sum(function ($item) use ($menuItems) {
                $menuItem = $menuItems->get($item['menu_item_id']);
                $base = 0;
                if ($menuItem) {
                    $sz = $item['size'] ?? null;
                    if ($sz === 'large') {
                        $base = $menuItem->large_price ?? $menuItem->price;
                    } elseif ($sz === 'medium') {
                        $base = $menuItem->medium_price ?? $menuItem->price;
                    } else {
                        $base = $menuItem->price;
                    }
                }
                $addOnsTotal = collect($item['add_ons'] ?? [])->sum(function($a){ return (float)($a['amount'] ?? 0); });
                return ($base + $addOnsTotal) * $item['quantity'];
            });

            $order = Order::create([
                'order_number'  => Str::upper(Str::random(10)),
                'customer_name' => $validated['customer_name'],
                'order_type'    => $validated['order_type'],
                'status'        => 'pending',
                'total_amount'  => $totalAmount,
            ]);

            $order->items()->createMany(
                collect($validated['items'])->map(function ($item) use ($menuItems) {
                    $menuItem = $menuItems->get($item['menu_item_id']);
                    $sz = $item['size'] ?? null;
                    $sz = is_string($sz) ? strtolower(trim($sz)) : null;
                    if (!in_array($sz, ['regular','medium','large'])) { $sz = null; }
                    $price = 0;
                    if ($menuItem) {
                        if ($sz === 'large') {
                            $price = $menuItem->large_price ?? $menuItem->price;
                        } elseif ($sz === 'medium') {
                            $price = $menuItem->medium_price ?? $menuItem->price;
                        } else {
                            $price = $menuItem->price;
                        }
                    }
                    $addOnsTotal = collect($item['add_ons'] ?? [])->sum(function($a){ return (float)($a['amount'] ?? 0); });
                    return [
                        'menu_item_id' => $item['menu_item_id'],
                        'quantity'     => $item['quantity'],
                        'subtotal'     => ($price + $addOnsTotal) * $item['quantity'],
                        'size'         => $sz,
                        'add_ons'      => $item['add_ons'] ?? [],
                    ];
                })->toArray()
            );

            // Update daily stats
            $this->updateDailyStats($order);

            DB::commit();
            
            // Clear dashboard cache
            Cache::forget('dashboard_stats_' . today()->toDateString());

            return response()->json([
                'message' => 'Order created successfully',
                'order'   => $order->load('items.menuItem.category'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Order creation failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // Update order fields (status, etc)
    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        
        $request->validate([
            'status' => 'required|string|in:pending,preparing,ready,completed'
        ]);
        
        $order->status = $request->status;
        $order->save();
        
        // Clear cache when order status changes
        Cache::forget('dashboard_stats_' . today()->toDateString());
        
        return response()->json([
            'message' => 'Order updated successfully',
            'order' => [
                'id' => $order->id,
                'status' => $order->status
            ]
        ], 200);
    }

    // Mark as completed directly
    public function markAsCompleted($id)
    {
        $order = Order::findOrFail($id);
        $order->update(['status' => 'completed']);
        
        // Clear cache
        Cache::forget('dashboard_stats_' . today()->toDateString());
        
        return response()->json([
            'message' => 'Order marked as completed',
            'order' => $order->load('items.menuItem.category')
        ]);
    }

    // Dashboard best selling item logic - OPTIMIZED WITH CACHING
    public function dashboardStats()
    {
        $cacheKey = 'dashboard_stats_' . today()->toDateString();
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () {
            // Single optimized query using joins and aggregates
            $stats = DB::table('orders')
                ->select(
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('COUNT(*) as order_count')
                )
                ->whereDate('created_at', today())
                ->where('status', 'completed')
                ->first();

            $revenueToday = (float) ($stats->revenue ?? 0);
            $orderCount = (int) ($stats->order_count ?? 0);

            // Find best selling item with single query
            $bestItemRow = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
                ->join('categories', 'menu_items.category_id', '=', 'categories.id')
                ->select(
                    'menu_items.name as item_name',
                    'categories.name as category_name',
                    DB::raw('SUM(order_items.quantity) as total_sold')
                )
                ->whereDate('orders.created_at', today())
                ->where('orders.status', 'completed')
                ->groupBy('order_items.menu_item_id', 'menu_items.name', 'categories.name')
                ->orderByDesc('total_sold')
                ->first();

            return response()->json([
                'today_revenue' => $revenueToday,
                'order_count' => $orderCount,
                'best_selling_item' => $bestItemRow->item_name ?? 'N/A',
                'best_selling_category' => $bestItemRow->category_name ?? 'N/A',
                'total_sold' => $bestItemRow->total_sold ?? 0,
            ]);
        });
    }

    // Admin weekly/daily statistics - OPTIMIZED
    public function adminStats()
    {
        $cacheKey = 'admin_stats_week_' . now()->startOfWeek()->toDateString();
        
        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $tz = config('app.timezone');
            $now = Carbon::now($tz);
            $start = (clone $now)->startOfWeek(Carbon::MONDAY);
            $end = (clone $start)->endOfWeek(Carbon::SUNDAY);

            // Single query to get all week's data
            $weeklyData = DB::table('orders')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total_amount) as revenue'),
                    DB::raw('COUNT(*) as order_count')
                )
                ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
                ->where('status', 'completed')
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            $weeklyRevenue = [];
            $weeklyOrders = [];
            $days = [];

            for ($d = 0; $d < 7; $d++) {
                $date = (clone $start)->addDays($d);
                $dateStr = $date->toDateString();
                $dayData = $weeklyData->get($dateStr);
                
                $weeklyRevenue[] = (float) ($dayData->revenue ?? 0);
                $weeklyOrders[] = (int) ($dayData->order_count ?? 0);
                $days[] = $dateStr;
            }

            // Get daily top items in a single query
            $dailyTopItemsData = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
                ->select(
                    DB::raw('DATE(orders.created_at) as date'),
                    'menu_items.name',
                    DB::raw('SUM(order_items.quantity) as total_sold'),
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY DATE(orders.created_at) ORDER BY SUM(order_items.quantity) DESC) as row_num')
                )
                ->whereBetween('orders.created_at', [$start->startOfDay(), $end->endOfDay()])
                ->where('orders.status', 'completed')
                ->groupBy(DB::raw('DATE(orders.created_at)'), 'order_items.menu_item_id', 'menu_items.name')
                ->get()
                ->where('row_num', 1)
                ->keyBy('date');

            $dailyTopItems = [];
            foreach ($days as $dateStr) {
                $topItem = $dailyTopItemsData->get($dateStr);
                $dailyTopItems[] = $topItem ? $topItem->name : 'N/A';
            }

            // Get weekly top item
            $topItemRow = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
                ->select(
                    'menu_items.name',
                    DB::raw('SUM(order_items.quantity) as total_sold')
                )
                ->whereBetween('orders.created_at', [$start->startOfDay(), $end->endOfDay()])
                ->where('orders.status', 'completed')
                ->groupBy('order_items.menu_item_id', 'menu_items.name')
                ->orderByDesc('total_sold')
                ->first();

            return response()->json([
                'weeklyRevenue' => $weeklyRevenue,
                'weeklyOrders' => $weeklyOrders,
                'days' => $days,
                'weeklyTopItem' => $topItemRow ? $topItemRow->name : 'N/A',
                'dailyTopItems' => $dailyTopItems,
            ]);
        });
    }

    public function show($id)
    {
        try {
            $order = Order::with(['items.menuItem:id,name,price,medium_price,large_price'])
                ->find($id);

            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            $items = $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => optional($item->menuItem)->name ?? 'Unknown Item',
                    'size' => $item->size,
                    'item_price' => $item->item_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                    'add_ons' => $item->add_ons,
                ];
            });

            return response()->json([
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'order_type' => $order->order_type,
                    'status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at->toIso8601String(),
                    'items' => $items,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update daily stats helper
     */
    private function updateDailyStats(Order $order): void
    {
        try {
            $tz = config('app.timezone');
            $date = $order->created_at->setTimezone($tz)->toDateString();

            DB::table('order_daily_stats')->updateOrInsert(
                ['stat_date' => $date],
                [
                    'revenue_sum' => DB::raw('revenue_sum + ' . (float) $order->total_amount),
                    'order_count' => DB::raw('order_count + 1'),
                    'updated_at' => now(),
                ]
            );

            // Update top item
            $bestDayItem = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereDate('orders.created_at', $date)
                ->select('order_items.menu_item_id', DB::raw('SUM(order_items.quantity) as total_sold'))
                ->groupBy('order_items.menu_item_id')
                ->orderByDesc('total_sold')
                ->first();

            if ($bestDayItem) {
                DB::table('order_daily_stats')
                    ->where('stat_date', $date)
                    ->update([
                        'top_item_id' => $bestDayItem->menu_item_id,
                        'top_item_quantity' => (int) $bestDayItem->total_sold,
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            // Swallow stats errors
        }
    }
}