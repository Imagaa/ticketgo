<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Get all active categories
     */
    public function index()
    {
        $categories = Category::where('is_active', true)
            ->withCount('events')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Get single category with events
     */
    public function show(Category $category)
    {
        $category->load(['events' => function ($query) {
            $query->published()->upcoming()->with(['venue', 'organizer']);
        }]);

        return response()->json([
            'data' => $category,
        ]);
    }

    /**
     * Create new category (Admin only)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'icon'        => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $category = Category::create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'icon'        => $validated['icon'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data'    => $category,
        ], 201);
    }

    /**
     * Update category (Admin only)
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'data'    => $category,
        ]);
    }

    /**
     * Delete category (Admin only)
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
