<?php

namespace App\Http\Controllers;

use App\Models\Product;
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

        $query = Product::with(['variants', 'categories']);

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

            return response()->json($products);
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($products);
    }

    /**
     * Show a single product by id or slug with related data.
     */
    public function show($id)
    {
        $product = Product::with(['variants', 'categories', 'reviews.user', 'reviews.images'])
            ->where('id', $id)
            ->orWhere('slug', $id)
            ->firstOrFail();

        $avg = $product->reviews()->avg('rating');

        $data = $product->toArray();
        $data['average_rating'] = $avg ? (float) number_format($avg, 2) : null;

        return response()->json($data);
    }
}
