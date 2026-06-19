<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Return a list of products with variants and categories.
     * Supports optional pagination via `per_page` query param.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $sort = $request->query('sort'); // e.g. name or -price
        $category = $request->query('category'); // id or slug
        $q = $request->query('q'); // search query
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $inStock = $request->boolean('in_stock', false);
        $attrs = $request->query('attr', []); // e.g. attr[color]=blue&attr[size]=M

        $query = Product::with(['variants.attributeValues', 'categories']);

        if ($category) {
            $query->whereHas('categories', function ($qcat) use ($category) {
                if (is_numeric($category)) {
                    $qcat->where('id', $category);
                } else {
                    $qcat->where('slug', $category);
                }
            });
        }

        if ($q) {
            $query->where(function ($s) use ($q) {
                $s->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('variants', function ($v) use ($q) {
                        $v->where('sku', 'like', "%{$q}%");
                    })
                    ->orWhereHas('categories', function ($c) use ($q) {
                        $c->where('name', 'like', "%{$q}%");
                    })
                    ->orWhereHas('variants.attributeValues', function ($av) use ($q) {
                        $av->where('value', 'like', "%{$q}%")
                            ->orWhere('display_value', 'like', "%{$q}%");
                    });
            });
        }

        // Filter by variant price, stock and attribute values
        if ($minPrice || $maxPrice || $inStock || ! empty($attrs)) {
            $query->whereHas('variants', function ($v) use ($minPrice, $maxPrice, $inStock, $attrs) {
                if ($minPrice !== null && $minPrice !== '') {
                    $v->where('price', '>=', (float) $minPrice);
                }
                if ($maxPrice !== null && $maxPrice !== '') {
                    $v->where('price', '<=', (float) $maxPrice);
                }
                if ($inStock) {
                    $v->where('stock', '>', 0);
                }

                // attribute filters: attr[name]=value or attr[name]=v1,v2
                foreach ($attrs as $attrName => $attrValue) {
                    if ($attrValue === null || $attrValue === '') {
                        continue;
                    }
                    $values = is_array($attrValue) ? $attrValue : explode(',', $attrValue);
                    $v->whereHas('attributeValues', function ($av) use ($attrName, $values) {
                        $av->whereHas('attributeType', function ($at) use ($attrName) {
                            $at->where('name', $attrName);
                        })->where(function ($q) use ($values) {
                            $q->whereIn('value', $values)
                                ->orWhereIn('display_value', $values);
                        });
                    });
                }
            });
        }

        if ($sort) {
            $direction = 'asc';
            if (str_starts_with($sort, '-')) {
                $direction = 'desc';
                $sort = ltrim($sort, '-');
            }
            // protect against invalid columns by allowing only certain fields
            $allowed = ['name', 'created_at', 'updated_at', 'price'];
            // support ordering by variant price (uses withMin)
            if ($sort === 'price') {
                $query->withMin('variants', 'price');
                if (in_array($sort, $allowed, true)) {
                    $query->orderBy('variants_min_price', $direction);
                }
            }
            if (in_array($sort, $allowed, true)) {
                // if price handled above, skip since already ordered
                if ($sort !== 'price') {
                    $query->orderBy($sort, $direction);
                }
            }
        }

        if ($perPage <= 0) {
            $products = $query->get();

            $items = $products->toArray();
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

        $products = $query->paginate($perPage, ['*'], 'page', $page);


        $payload = [
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $products->items(),
                'pagination' => [
                    'page' => $products->currentPage(),
                    'limit' => $products->perPage(),
                    'totalItems' => $products->total(),
                    'totalPages' => $products->lastPage(),
                ],
            ],
        ];

        return response()->json($payload, 200);

    }

    /**
     * Show a single product by id or slug with related data.
     */
    public function show($id)
    {
        $productQuery = Product::with(['variants.attributeValues', 'categories', 'reviews.user', 'reviews.images']);

        if (is_numeric($id)) {
            $product = $productQuery->where('id', $id)->firstOrFail();
        } elseif (Schema::hasColumn('products', 'slug')) {
            $product = $productQuery->where('slug', $id)->firstOrFail();
        } else {
            abort(404);
        }

        $avg = $product->reviews()->avg('rating');

        $data = $product->toArray();
        $data['average_rating'] = $avg ? (float) number_format($avg, 2) : null;

        $payload = [
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $data,
                'pagination' => null,
            ],
        ];

        return response()->json($payload, 200);
    }

    /**
     * Store a new product (admin only).
     */
    public function store(Request $request)
    {
        // require admin role
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
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'image' => 'nullable|string',
            // categories/variants are accepted flexibly (single id as string/int or arrays)
            'categories' => 'nullable',
            'variants' => 'nullable',
        ]);

        $product = new Product();
        $product->name = $validated['name'];
        $product->description = $validated['description'] ?? null;
        $product->price = $validated['price'];
        $product->discount_price = $validated['discount_price'] ?? null;
        $product->image = $validated['image'] ?? null;
        $product->save();

        // Attach categories if provided. Accept single id (string/int) or array of ids/objects.
        if (array_key_exists('categories', $validated) && $validated['categories'] !== null) {
            $catsRaw = $validated['categories'];
            if (!is_array($catsRaw)) {
                $cats = [$catsRaw];
            } else {
                $cats = $catsRaw;
            }
            $catIds = array_map(function ($c) {
                if (is_array($c) && isset($c['id'])) return (int) $c['id'];
                return (int) $c;
            }, $cats);
            $catIds = array_filter($catIds, fn($v) => $v > 0);
            if (!empty($catIds)) {
                $product->categories()->sync($catIds);
            }
        }

        // Handle variants input. Accept:
        // - single id (string/int) => attach existing variant(s) to this product by updating product_id
        // - array of ids => attach existing variants
        // - array of objects => create new variants
        if (array_key_exists('variants', $validated) && $validated['variants'] !== null) {
            $varsRaw = $validated['variants'];
            if (!is_array($varsRaw)) {
                // single id
                $vid = (int) $varsRaw;
                $existing = ProductVariant::find($vid);
                if ($existing) {
                    $existing->product_id = $product->id;
                    $existing->save();
                }
            } else {
                // array provided
                $first = reset($varsRaw);
                if (is_array($first) && array_key_exists('sku', $first)) {
                    // array of objects => create
                    foreach ($varsRaw as $v) {
                        $pv = new ProductVariant();
                        $pv->sku = $v['sku'] ?? null;
                        if ($pv->sku && ProductVariant::where('sku', $pv->sku)->exists()) {
                            $pv->sku = $pv->sku . '-' . uniqid();
                        }
                        $pv->price = $v['price'] ?? 0;
                        $pv->discount_price = $v['discount_price'] ?? null;
                        $pv->stock = $v['stock'] ?? 0;
                        $pv->image = $v['image'] ?? null;
                        $pv->product_id = $product->id;
                        $pv->save();

                        // attach attribute values if provided (array of ids)
                        if (!empty($v['attribute_value_ids']) && is_array($v['attribute_value_ids'])) {
                            $pv->attributeValues()->sync(array_map('intval', $v['attribute_value_ids']));
                        } elseif (!empty($v['attribute_values']) && is_array($v['attribute_values'])) {
                            // accept array of attribute value objects or values (try id first)
                            $ids = array_map(function ($it) {
                                if (is_array($it) && isset($it['id'])) return (int)$it['id'];
                                return (int)$it;
                            }, $v['attribute_values']);
                            $pv->attributeValues()->sync(array_filter($ids));
                        }
                    }
                } else {
                    // array of ids (or strings)
                    foreach ($varsRaw as $vid) {
                        $existing = ProductVariant::find((int) $vid);
                        if ($existing) {
                            $existing->product_id = $product->id;
                            $existing->save();
                        }
                    }
                }
            }
        }

        $product->load(['variants', 'categories']);

        $payload = [
            'status' => 201,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $product->toArray(),
                'pagination' => null,
            ],
        ];

        return response()->json($payload, 201);
    }

    /**
     * Update a product (admin only).
     */
    public function update(Request $request, $id)
    {
        // require admin role
        $user = $request->user();
        if (!$user || (($user->role ?? null) !== 'admin')) {
            return response()->json([
                'status' => 403,
                'success' => false,
                'message' => 'Forbidden: admin only',
                'data' => null,
            ], 403);
        }

        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric',
            'discount_price' => 'nullable|numeric',
            'image' => 'nullable|string',
            // flexible inputs
            'categories' => 'nullable',
            'variants' => 'nullable',
        ]);

        // Update scalar fields
        $updatable = ['name', 'description', 'price', 'discount_price', 'image'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $validated)) {
                $product->{$field} = $validated[$field];
            }
        }
        $product->save();

        // Handle categories: accept single id or array
        if (array_key_exists('categories', $validated) && $validated['categories'] !== null) {
            $catsRaw = $validated['categories'];
            if (!is_array($catsRaw)) {
                $cats = [$catsRaw];
            } else {
                $cats = $catsRaw;
            }
            $catIds = array_map(function ($c) {
                if (is_array($c) && isset($c['id'])) return (int) $c['id'];
                return (int) $c;
            }, $cats);
            $catIds = array_filter($catIds, fn($v) => $v > 0);
            $product->categories()->sync($catIds);
        }

        // Handle variants: single id / array of ids / array of objects
        if (array_key_exists('variants', $validated) && $validated['variants'] !== null) {
            $varsRaw = $validated['variants'];
            if (!is_array($varsRaw)) {
                $vid = (int) $varsRaw;
                $existing = ProductVariant::find($vid);
                if ($existing) {
                    $existing->product_id = $product->id;
                    $existing->save();
                }
            } else {
                $first = reset($varsRaw);
                if (is_array($first) && array_key_exists('sku', $first)) {
                    // array of objects => create new variants and assign
                    foreach ($varsRaw as $v) {
                        $pv = null;
                        if (!empty($v['id'])) {
                            $pv = ProductVariant::find((int)$v['id']);
                        }
                        if ($pv) {
                            // update existing
                            if (array_key_exists('sku', $v)) {
                                $pv->sku = $v['sku'] ?? $pv->sku;
                                if ($pv->sku && ProductVariant::where('sku', $pv->sku)->where('id', '!=', $pv->id)->exists()) {
                                    $pv->sku = $pv->sku . '-' . uniqid();
                                }
                            }
                            if (array_key_exists('price', $v)) $pv->price = $v['price'];
                            if (array_key_exists('discount_price', $v)) $pv->discount_price = $v['discount_price'];
                            if (array_key_exists('stock', $v)) $pv->stock = $v['stock'];
                            if (array_key_exists('image', $v)) $pv->image = $v['image'];
                            $pv->product_id = $product->id;
                            $pv->save();
                        } else {
                            // create new
                            $pv = new ProductVariant();
                            $pv->sku = $v['sku'] ?? null;
                            if ($pv->sku && ProductVariant::where('sku', $pv->sku)->exists()) {
                                $pv->sku = $pv->sku . '-' . uniqid();
                            }
                            $pv->price = $v['price'] ?? 0;
                            $pv->discount_price = $v['discount_price'] ?? null;
                            $pv->stock = $v['stock'] ?? 0;
                            $pv->image = $v['image'] ?? null;
                            $pv->product_id = $product->id;
                            $pv->save();
                        }

                        // sync attribute values if provided
                        if (!empty($v['attribute_value_ids']) && is_array($v['attribute_value_ids'])) {
                            $pv->attributeValues()->sync(array_map('intval', $v['attribute_value_ids']));
                        } elseif (!empty($v['attribute_values']) && is_array($v['attribute_values'])) {
                            $ids = array_map(function ($it) {
                                if (is_array($it) && isset($it['id'])) return (int)$it['id'];
                                return (int)$it;
                            }, $v['attribute_values']);
                            $pv->attributeValues()->sync(array_filter($ids));
                        }
                    }
                } else {
                    // array of ids -> make these the product's variants; unassign others
                    $newIds = array_map(fn($v) => (int)$v, $varsRaw);
                    // remove variants that currently belong to this product but are not in newIds
                    // but skip deleting any variants that are referenced by order_details
                    $toRemove = ProductVariant::where('product_id', $product->id)
                        ->whereNotIn('id', $newIds)
                        ->get();
                    $blocked = [];
                    foreach ($toRemove as $pv) {
                        $hasOrders = OrderDetail::where('product_variant_id', $pv->id)->exists();
                        if ($hasOrders) {
                            $blocked[] = $pv->id;
                        } else {
                            $pv->delete();
                        }
                    }

                    foreach ($newIds as $vid) {
                        $existing = ProductVariant::find((int) $vid);
                        if ($existing) {
                            $existing->product_id = $product->id;
                            $existing->save();
                        }
                    }
                }
            }
        }

        $product->load(['variants', 'categories']);

        $message = null;
        if (!empty($blocked ?? [])) {
            $message = 'Some variants could not be removed because they are referenced by order_details: ' . implode(',', $blocked);
        }

        $payload = [
            'status' => 200,
            'success' => true,
            'message' => $message,
            'data' => [
                'items' => $product->toArray(),
                'pagination' => null,
            ],
        ];

        return response()->json($payload, 200);
    }

    /**
     * Delete a product (admin only).
     */
    public function destroy(Request $request, $id)
    {
        // require admin role
        $user = $request->user();
        if (!$user || (($user->role ?? null) !== 'admin')) {
            return response()->json([
                'status' => 403,
                'success' => false,
                'message' => 'Forbidden: admin only',
                'data' => null,
            ], 403);
        }

        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Product deleted',
            'data' => null,
        ], 200);
    }
}
