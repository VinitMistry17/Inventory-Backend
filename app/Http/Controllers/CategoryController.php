<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $request->user()->id);
            })
            ->get(['id', 'name', 'icon', 'is_custom']);

        return response()->json($categories, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $category = Category::create([
            'name' => $validator->validated()['name'],
            'icon' => 'default_folder',
            'is_custom' => true,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
            'icon' => $category->icon,
            'is_custom' => $category->is_custom,
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        if ($category->user_id !== $request->user()->id) {
            if (! $category->is_custom) {
                return response()->json([
                    'message' => 'Forbidden',
                ], 403);
            }

            return response()->json([
                'message' => 'Category not found',
            ], 404);
        }

        if (! $category->is_custom) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ], 200);
    }
}
