<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MenuItem;

class MenuItemController extends Controller
{
    public function index()
    {
        return MenuItem::with('category')->get();
    }

    public function show(MenuItem $menuItem)
    {
        return $menuItem;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'is_available' => 'sometimes|boolean',
            'category_id' => 'required|exists:categories,id',
        ]);

        if (!array_key_exists('is_available', $data)) {
            $data['is_available'] = true;
        }

        $item = MenuItem::create($data);
        return response()->json($item, 201);
    }

    public function update(Request $request, MenuItem $menuItem)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'is_available' => 'sometimes|boolean',
            'category_id' => 'sometimes|exists:categories,id',
        ]);

        $menuItem->update($data);
        return $menuItem;
    }

    public function destroy(MenuItem $menuItem)
    {
        $menuItem->delete();
        return response()->noContent();
    }
}