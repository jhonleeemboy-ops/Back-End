<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    // List all orders with nested relationships
    public function index()
    {
        $orders = Order::with(['items.menuItem.category'])
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
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = collect($validated['items'])->sum(function ($item) {
                $menuItem = MenuItem::find($item['menu_item_id']);
                return ($menuItem ? $menuItem->price : 0) * $item['quantity'];
            });

            $order = Order::create([
                'order_number'  => Str::upper(Str::random(10)),
                'customer_name' => $validated['customer_name'],
                'order_type'    => $validated['order_type'],
                'status'        => 'pending',
                'total_amount'  => $totalAmount,
            ]);

            $order->items()->createMany(
                collect($validated['items'])->map(function ($item) {
                    $menuItem = MenuItem::find($item['menu_item_id']);
                    return [
                        'menu_item_id' => $item['menu_item_id'],
                        'item_price'   => $menuItem->price,
                        'quantity'     => $item['quantity'],
                        'subtotal'     => $menuItem->price * $item['quantity'],
                    ];
                })->toArray()
            );

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
        if ($request->has('status')) {
            $order->status = $request->input('status');
        }
        $order->save();
        return response()->json([
            'message' => 'Order updated successfully',
            'order'   => $order->load('items.menuItem.category')
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
    public function dashboardStats()
{
    $revenueToday = Order::whereDate('created_at', today())->sum('total_amount');
    $orderCount = Order::whereDate('created_at', today())->count();

    $bestItemRow = DB::table('order_items')
        ->select('menu_item_id', DB::raw('SUM(quantity) as total_sold'))
        ->whereIn('order_id', function ($query) {
            $query->select('id')
                ->from('orders')
                ->whereDate('created_at', today());
        })
        ->groupBy('menu_item_id')
        ->orderByDesc('total_sold')
        ->first();

    $bestSellingItem = $bestItemRow ? 
        MenuItem::with('category')->find($bestItemRow->menu_item_id) : 
        null;

    return response()->json([
        'today_revenue' => $revenueToday ?? 0,
        'order_count' => $orderCount ?? 0,
        'best_selling_item' => $bestSellingItem->name ?? 'N/A',
        'best_selling_category' => $bestSellingItem->category->name ?? 'N/A',
        'total_sold' => $bestItemRow->total_sold ?? 0,
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
                'item_price' => $item->item_price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
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
