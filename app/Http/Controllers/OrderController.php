<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OrderController extends Controller
{
    // List all orders with nested relationships
    public function index()
    {
        $orders = Order::select('id','order_number','customer_name','order_type','status','total_amount','created_at')
            ->with([
                'items' => function($q){
                    $q->select(['id','order_id','menu_item_id','quantity','subtotal','size','add_ons']);
                },
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

            // Update daily totals (no hourly columns required)
            try {
                $tz = config('app.timezone');
                $date = $order->created_at->setTimezone($tz)->toDateString();

                $existing = DB::table('order_daily_stats')->where('stat_date', $date)->first();
                if ($existing) {
                    DB::table('order_daily_stats')
                        ->where('id', $existing->id)
                        ->update([
                            'revenue_sum' => DB::raw('revenue_sum + ' . (float) $order->total_amount),
                            'order_count' => DB::raw('order_count + 1'),
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('order_daily_stats')
                        ->insert([
                            'stat_date' => $date,
                            'revenue_sum' => (float) $order->total_amount,
                            'order_count' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

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
                // swallow stats errors to not impact order creation
            }

            DB::commit();

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
    
    // Only allow updating status
    $request->validate([
        'status' => 'required|string|in:pending,preparing,ready,completed'
    ]);
    
    $order->status = $request->status;
    $order->save();
    
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
        return response()->json([
            'message' => 'Order marked as completed',
            'order' => $order->load('items.menuItem.category')
        ]);
    }

    // Dashboard best selling item logic
    // Optimized to reduce DB calls by combining aggregates
    public function dashboardStats()
    {
           
           $orders = Order::with(['items'])
           ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->get();

            $revenueToday = 0.0;
        foreach ($orders as $order) {
            foreach ($order->items as $it) {
                $rawAddOns = $it->add_ons ?? [];
                $addOns = is_string($rawAddOns) ? (json_decode($rawAddOns, true) ?: []) : (is_array($rawAddOns) ? $rawAddOns : []);
                $addTotal = 0.0;
                foreach ($addOns as $a) {
                    $addTotal += (float) ($a['amount'] ?? 0);
                }
                $revenueToday += ((float) $it->item_price * (int) ($it->quantity ?? 0)) + $addTotal;
            }
        }
        $orderCount = $orders->count();

        // Find best selling item
        $bestItemRow = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereDate('orders.created_at', today())
            ->where('orders.status', 'completed')
            ->select('order_items.menu_item_id', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('order_items.menu_item_id')
            ->orderByDesc('total_sold')
            ->first();

        // Eager load category to avoid N+1 if we were fetching multiple, though here it's just one
        $bestSellingItem = $bestItemRow ? 
            MenuItem::with('category')->find($bestItemRow->menu_item_id) : 
            null;

        return response()->json([
            'today_revenue' => $revenueToday,
            'order_count' => $orderCount,
            'best_selling_item' => $bestSellingItem->name ?? 'N/A',
            'best_selling_category' => $bestSellingItem->category->name ?? 'N/A',
            'total_sold' => $bestItemRow->total_sold ?? 0,
        ]);
    }

    // Admin weekly/daily statistics
    public function adminStats()
    {
        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $start = (clone $now)->startOfWeek(Carbon::MONDAY);
        $end = (clone $start)->endOfWeek(Carbon::SUNDAY);

        $weeklyRevenue = [];
        $weeklyOrders = [];
        $days = [];
        $dailyTopItems = [];

        for ($d = 0; $d < 7; $d++) {
            $date = (clone $start)->addDays($d);
            $orders = Order::whereBetween('created_at', [
                    (clone $date)->startOfDay(),
                    (clone $date)->endOfDay()
                ])
                ->where('status', 'completed')
                ->get(['total_amount']);
            $weeklyRevenue[] = (float) $orders->sum('total_amount');
            $weeklyOrders[] = (int) $orders->count();
            $days[] = $date->toDateString();

            $topRow = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereDate('orders.created_at', $date->toDateString())
                ->where('orders.status', 'completed')
                ->select('order_items.menu_item_id', DB::raw('SUM(order_items.quantity) as total_sold'))
                ->groupBy('order_items.menu_item_id')
                ->orderByDesc('total_sold')
                ->first();
            $dailyTopItems[] = $topRow ? (MenuItem::find($topRow->menu_item_id)->name ?? 'N/A') : 'N/A';
        }

        $topItemRow = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [
                (clone $start)->startOfDay(),
                (clone $end)->endOfDay()
            ])
            ->where('orders.status', 'completed')
            ->select('order_items.menu_item_id', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('order_items.menu_item_id')
            ->orderByDesc('total_sold')
            ->first();

        $weeklyTopItem = $topItemRow ? MenuItem::find($topItemRow->menu_item_id) : null;

        return response()->json([
            'weeklyRevenue' => $weeklyRevenue,
            'weeklyOrders' => $weeklyOrders,
            'days' => $days,
            'weeklyTopItem' => $weeklyTopItem ? $weeklyTopItem->name : 'N/A',
            'dailyTopItems' => $dailyTopItems,
        ]);
    }

    public function show($id)
    {
        try {
            $order = Order::with(['items.menuItem'])->find($id);

            if (!$order) {
                return response()->json([
                    'error' => 'Order not found'
                ], 404);
            }

            // Format items for frontend
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

            // Final order structure
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
}
