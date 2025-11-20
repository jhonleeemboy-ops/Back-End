<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * List all orders with their items.
     */
    public function index()
    {
        // Eager load items for performance
        $orders = Order::with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => $orders,
        ], 200);
    }

    /**
     * Store a new order and its items.
     */
    public function store(Request $request)
    {
        // Validate incoming data
        $validated = $request->validate([
            'customer_name'      => 'required|string',
            'order_type'         => 'required|in:dine-in,takeout',
            'items'              => 'required|array|min:1',
            'items.*.item_name'  => 'required|string',
            'items.*.item_price' => 'required|numeric',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        // Calculate total amount
        $totalAmount = collect($validated['items'])->sum(function ($item) {
            return $item['item_price'] * $item['quantity'];
        });

        DB::beginTransaction();

        try {
            // Create the order
            $order = Order::create([
                'order_number' => Str::upper(Str::random(10)),
                'customer_name' => $validated['customer_name'],
                'order_type'    => $validated['order_type'],
                'status'        => 'pending',
                'total_amount'  => $totalAmount,
            ]);

            // Create related order items
            $order->items()->createMany(
                collect($validated['items'])->map(function ($item) {
                    return [
                        'item_name'  => $item['item_name'],
                        'item_price' => $item['item_price'],
                        'quantity'   => $item['quantity'],
                        'subtotal'   => $item['item_price'] * $item['quantity'],
                    ];
                })->toArray()
            );

            DB::commit();

            // Return the created order (with items) as JSON
            return response()->json([
                'message' => 'Order created successfully',
                'order'   => $order->load('items'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Order creation failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
