<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

        $query = Category::with('children');

        if ($perPage <= 0) {
            $items = $query->get()->toArray();
            $payload = [
                'status' => 200,
                'success' => true,
                'message' => null,
                'data' => [
                    'items' => $items,
                    'pagination' => null,
                ],
            ];

            return response()->json($payload, 200);
        }

        $cats = $query->paginate($perPage, ['*'], 'page', $page);

        $payload = [
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $cats->items(),
                'pagination' => [
                    'page' => $cats->currentPage(),
                    'limit' => $cats->perPage(),
                    'totalItems' => $cats->total(),
                    'totalPages' => $cats->lastPage(),
                ],
            ],
        ];

        return response()->json($payload, 200);
    }

    public function show($id)
    {
        $cat = Category::with(['children','parent'])->findOrFail($id);

        $payload = [
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $cat->toArray(),
                'pagination' => null,
            ],
        ];

        return response()->json($payload, 200);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || (($user->role ?? null) !== 'admin')) {
            return response()->json([
                'status' => 403,
                'success' => false,
                'message' => 'Forbidden: admin only',
                'data' => null,
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        $cat = new Category();
        $cat->name = $validated['name'];
        $cat->parent_id = $validated['parent_id'] ?? null;
        $cat->save();

        $payload = [
            'status' => 201,
            'success' => true,
            'message' => null,
            'data' => [ 'items' => $cat->toArray(), 'pagination' => null ],
        ];

        return response()->json($payload, 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || (($user->role ?? null) !== 'admin')) {
            return response()->json([
                'status' => 403,
                'success' => false,
                'message' => 'Forbidden: admin only',
                'data' => null,
            ], 403);
        }

        $cat = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        if (array_key_exists('name', $validated)) $cat->name = $validated['name'];
        if (array_key_exists('parent_id', $validated)) $cat->parent_id = $validated['parent_id'];

        $cat->save();

        $payload = [
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [ 'items' => $cat->toArray(), 'pagination' => null ],
        ];

        return response()->json($payload, 200);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || (($user->role ?? null) !== 'admin')) {
            return response()->json([
                'status' => 403,
                'success' => false,
                'message' => 'Forbidden: admin only',
                'data' => null,
            ], 403);
        }

        $cat = Category::findOrFail($id);
        $cat->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Category deleted',
            'data' => null,
        ], 200);
    }
}
