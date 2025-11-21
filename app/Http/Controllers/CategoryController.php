<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::orderBy('display_order')->get();
    }

    public function show(Category $category)
    {
        return $category;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'display_order' => 'nullable|integer',
        ]);

        $category = Category::create([
            'name' => $data['name'],
            'display_order' => $data['display_order'] ?? 0,
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'display_order' => 'sometimes|integer',
        ]);

        $category->update($data);

        return $category;
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->noContent();
    }
}