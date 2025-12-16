<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MenuItemController extends Controller
{
    public function index()
    {
        return MenuItem::with('category')->get();
    }

    public function show(MenuItem $menuItem)
    {
        return $menuItem->load('category');
    }

    public function store(Request $request)
    {
        Log::info('Store menu item request', [
            'has_file' => $request->hasFile('image'),
            'all_data' => $request->except(['image'])
        ]);

        $data = $request->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'medium_price' => 'nullable|numeric',
            'large_price' => 'nullable|numeric',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'is_available' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        if (!array_key_exists('is_available', $data)) {
            $data['is_available'] = true;
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu-images', 'public');
            $data['image_url'] = '/storage/' . $path;
            Log::info('Image uploaded', ['path' => $data['image_url']]);
        }

        $item = MenuItem::create($data);
        Log::info('Menu item created', ['item' => $item]);
        
        return response()->json($item->load('category'), 201);
    }

    public function update(Request $request, MenuItem $menuItem)
    {
        Log::info('Update menu item request', [
            'id' => $menuItem->id,
            'method' => $request->method(),
            'has_file' => $request->hasFile('image'),
            'remove_image' => $request->input('remove_image'),
            'all_data' => $request->except(['image'])
        ]);

        $data = $request->validate([
            'name' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'medium_price' => 'nullable|numeric',
            'large_price' => 'nullable|numeric',
            'description' => 'nullable|string',
            'category_id' => 'sometimes|exists:categories,id',
            'is_available' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        // Handle image removal
        $removeImage = $request->input('remove_image') === '1' || $request->boolean('remove_image', false);
        if ($removeImage && $menuItem->image_url) {
            $relative = str_replace('/storage/', '', $menuItem->image_url);
            $relative = ltrim($relative, '/');
            if ($relative) {
                Storage::disk('public')->delete($relative);
                Log::info('Old image deleted', ['path' => $relative]);
            }
            $data['image_url'] = null;
        }

        // Handle new image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($menuItem->image_url) {
                $relative = str_replace('/storage/', '', $menuItem->image_url);
                $relative = ltrim($relative, '/');
                if ($relative) {
                    Storage::disk('public')->delete($relative);
                    Log::info('Replacing old image', ['old_path' => $relative]);
                }
            }
            
            $path = $request->file('image')->store('menu-images', 'public');
            $data['image_url'] = '/storage/' . $path;
            Log::info('New image uploaded', ['path' => $data['image_url']]);
        }

        $menuItem->update($data);
        Log::info('Menu item updated', ['item' => $menuItem]);
        
        return response()->json($menuItem->load('category'));
    }

    public function destroy(MenuItem $menuItem)
    {
        // Delete image if exists
        if ($menuItem->image_url) {
            $relative = str_replace('/storage/', '', $menuItem->image_url);
            $relative = ltrim($relative, '/');
            if ($relative) {
                Storage::disk('public')->delete($relative);
                Log::info('Image deleted on item deletion', ['path' => $relative]);
            }
        }
        
        $menuItem->delete();
        Log::info('Menu item deleted', ['id' => $menuItem->id]);
        
        return response()->noContent();
    }
}