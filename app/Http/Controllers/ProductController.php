<?php

namespace App\Http\Controllers;

use App\Models\AttributeType;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    /**
     * Trả về danh sách sản phẩm kèm biến thể và danh mục.
     * Hỗ trợ phân trang bằng query `per_page`.
     */
    #[OA\Get(
        path: '/api/products',
        summary: 'Danh sách sản phẩm',
        tags: ['Sản phẩm'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 20),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '-price'),
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: '1'),
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'áo thun'),
            new OA\Parameter(name: 'min_price', in: 'query', required: false, schema: new OA\Schema(type: 'number'), example: 100000),
            new OA\Parameter(name: 'max_price', in: 'query', required: false, schema: new OA\Schema(type: 'number'), example: 500000),
            new OA\Parameter(name: 'in_stock', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true),
            new OA\Parameter(name: 'promotionId', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy danh sách sản phẩm thành công'),
        ]
    )]
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
        $promotionId = $request->query('promotionId');

        $query = Product::with(['variants.attributeValues', 'categories']);

        if ($promotionId !== null && $promotionId !== '') {
            $query->whereHas('promotions', function ($promotionQuery) use ($promotionId) {
                $promotionQuery->where('promotions.id', (int) $promotionId);
            });
        }

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
     * Hiển thị một sản phẩm theo id hoặc slug kèm dữ liệu liên quan.
     */
    #[OA\Get(
        path: '/api/products/{id}',
        summary: 'Chi tiết sản phẩm',
        tags: ['Sản phẩm'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '1'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy thông tin sản phẩm thành công'),
            new OA\Response(response: 404, description: 'Không tìm thấy sản phẩm'),
        ]
    )]
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

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $data,
                'pagination' => null,
            ],
        ], 200);
    }

    private function ensureAdmin($user): void
    {
        if (! $user || $user->role !== 'ROLE_ADMIN') {
            abort(403, 'Chỉ quản trị viên được phép');
        }
    }

    private function formatInventoryItem(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'product_id' => $variant->product_id,
            'product_name' => $variant->product?->name,
            'price' => (float) $variant->price,
            'stock' => (int) $variant->stock,
            'updated_at' => $variant->updated_at?->toISOString(),
        ];
    }

    public function adminInventoryIndex(Request $request)
    {
        $this->ensureAdmin($request->user());

        $perPage = max(1, (int) $request->query('per_page', 20));
        $page = max(1, (int) $request->query('page', 1));
        $q = trim((string) $request->query('q', ''));
        $lowStock = $request->boolean('low_stock', false);

        $query = ProductVariant::query()->with('product');

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('sku', 'like', "%{$q}%")
                    ->orWhereHas('product', function ($productQuery) use ($q) {
                        $productQuery->where('name', 'like', "%{$q}%");
                    });
            });
        }

        if ($lowStock) {
            $query->where('stock', '<=', 10);
        }

        $variants = $query->orderBy('stock', 'asc')
            ->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $variants->getCollection()->map(fn (ProductVariant $variant) => $this->formatInventoryItem($variant)),
                'pagination' => [
                    'page' => $variants->currentPage(),
                    'limit' => $variants->perPage(),
                    'totalItems' => $variants->total(),
                    'totalPages' => $variants->lastPage(),
                ],
            ],
        ], 200);
    }

    #[OA\Patch(
        path: '/api/admin/inventory/{productVariant}',
        summary: 'Cập nhật tồn kho biến thể sản phẩm',
        security: [['bearerAuth' => []]],
        tags: ['Quản lý tồn kho'],
        parameters: [
            new OA\Parameter(name: 'productVariant', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['stock'],
                properties: [
                    new OA\Property(property: 'stock', type: 'integer', example: 25),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật tồn kho thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function adminInventoryUpdate(Request $request, ProductVariant $productVariant)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        $productVariant->update([
            'stock' => $validated['stock'],
        ]);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $this->formatInventoryItem($productVariant->fresh(['product'])),
        ], 200);
    }

    #[OA\Post(
        path: '/api/admin/inventory/stock-in',
        summary: 'Nhập kho cho biến thể sản phẩm',
        security: [['bearerAuth' => []]],
        tags: ['Quản lý tồn kho'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['variant_id', 'quantity'],
                properties: [
                    new OA\Property(property: 'variant_id', type: 'integer', example: 12),
                    new OA\Property(property: 'quantity', type: 'integer', example: 10),
                    new OA\Property(property: 'note', type: 'string', example: 'Nhập hàng từ nhà cung cấp'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Nhập kho thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function adminInventoryStockIn(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $variant = ProductVariant::findOrFail($validated['variant_id']);
        $variant->stock += $validated['quantity'];
        $variant->save();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Nhập kho thành công',
            'data' => $this->formatInventoryItem($variant->fresh(['product'])),
        ], 200);
    }

    #[OA\Post(
        path: '/api/admin/inventory/stock-out',
        summary: 'Xuất kho cho biến thể sản phẩm',
        security: [['bearerAuth' => []]],
        tags: ['Quản lý tồn kho'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['variant_id', 'quantity'],
                properties: [
                    new OA\Property(property: 'variant_id', type: 'integer', example: 12),
                    new OA\Property(property: 'quantity', type: 'integer', example: 3),
                    new OA\Property(property: 'note', type: 'string', example: 'Xuất bán cho đơn hàng'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Xuất kho thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 422, description: 'Số lượng xuất vượt quá tồn kho'),
        ]
    )]
    public function adminInventoryStockOut(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $variant = ProductVariant::findOrFail($validated['variant_id']);

        if ($variant->stock < $validated['quantity']) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Số lượng xuất vượt quá tồn kho hiện có.',
                'data' => null,
            ], 422);
        }

        $variant->stock -= $validated['quantity'];
        $variant->save();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Xuất kho thành công',
            'data' => $this->formatInventoryItem($variant->fresh(['product'])),
        ], 200);
    }

    public function create()
    {
        $attributeTypes = AttributeType::with('attributeValues')->get();

        return view('admin.product_create', [
            'attributeTypes' => $attributeTypes,
        ]);
    }

    /**
     * Tạo sản phẩm mới, chỉ dành cho admin.
     */
    #[OA\Post(
        path: '/api/products',
        summary: 'Tạo sản phẩm',
        security: [['bearerAuth' => []]],
        tags: ['Sản phẩm'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Áo thun basic'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Áo thun cotton mềm'),
                    new OA\Property(property: 'price', type: 'number', example: 199000),
                    new OA\Property(property: 'discount_price', type: 'number', nullable: true, example: 149000),
                    new OA\Property(property: 'image', type: 'string', nullable: true, example: 'products/ao-thun.jpg'),
                    new OA\Property(
                        property: 'categories',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2]
                    ),
                    new OA\Property(
                        property: 'variants',
                        type: 'array',
                        items: new OA\Items(
                            required: ['sku', 'price'],
                            properties: [
                                new OA\Property(property: 'sku', type: 'string', example: 'AO-THUN-DEN-M'),
                                new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, example: 199000),
                                new OA\Property(property: 'discount_price', type: 'number', format: 'float', minimum: 0, nullable: true, example: 169000),
                                new OA\Property(property: 'stock', type: 'integer', minimum: 0, default: 0, example: 10),
                                new OA\Property(
                                    property: 'image',
                                    description: 'Ảnh riêng của biến thể. Nếu không truyền, hệ thống sử dụng ảnh sản phẩm.',
                                    type: 'string',
                                    nullable: true,
                                    example: 'https://cdn.example.com/products/ao-thun-den.jpg'
                                ),
                                new OA\Property(
                                    property: 'attribute_value_ids',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [1, 2]
                                ),
                            ],
                            type: 'object'
                        )
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tạo sản phẩm thành công'),
            new OA\Response(response: 403, description: 'Chỉ admin được phép thao tác'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'discount_price' => 'nullable|numeric',
            'image' => 'nullable|string',
            'attribute_value_ids' => 'nullable|array',
            'attribute_value_ids.*' => 'integer',
            // categories/variants are accepted flexibly (single id as string/int or arrays)
            'categories' => 'nullable',
            'variants' => 'nullable',
            'variants.*.sku' => 'sometimes|required|string|max:255',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.discount_price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|integer|min:0',
            'variants.*.image' => 'nullable|string|max:2048',
            'variants.*.attribute_value_ids' => 'nullable|array',
            'variants.*.attribute_value_ids.*' => 'integer',
        ]);

        $product = new Product;
        $product->name = $validated['name'];
        $product->description = $validated['description'] ?? null;
        $product->price = $validated['price'];
        $product->discount_price = $validated['discount_price'] ?? null;
        $product->image = $validated['image'] ?? null;
        $product->save();

        // Attach categories if provided. Accept single id (string/int) or array of ids/objects.
        if (array_key_exists('categories', $validated) && $validated['categories'] !== null) {
            $catsRaw = $validated['categories'];
            if (! is_array($catsRaw)) {
                $cats = [$catsRaw];
            } else {
                $cats = $catsRaw;
            }
            $catIds = array_map(function ($c) {
                if (is_array($c) && isset($c['id'])) {
                    return (int) $c['id'];
                }

                return (int) $c;
            }, $cats);
            $catIds = array_filter($catIds, fn ($v) => $v > 0);
            if (! empty($catIds)) {
                $product->categories()->sync($catIds);
            }
        }

        // Handle variants input. Accept:
        // - single id (string/int) => attach existing variant(s) to this product by updating product_id
        // - array of ids => attach existing variants
        // - array of objects => create new variants
        if (array_key_exists('variants', $validated) && $validated['variants'] !== null) {
            $varsRaw = $validated['variants'];
            if (! is_array($varsRaw)) {
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
                        $pv = new ProductVariant;
                        $pv->sku = $v['sku'] ?? null;
                        if ($pv->sku && ProductVariant::where('sku', $pv->sku)->exists()) {
                            $pv->sku = $pv->sku.'-'.uniqid();
                        }
                        $pv->price = $v['price'] ?? 0;
                        $pv->discount_price = $v['discount_price'] ?? null;
                        $pv->stock = $v['stock'] ?? 0;
                        $pv->image = ! empty($v['image']) ? $v['image'] : $product->image;
                        $pv->product_id = $product->id;
                        $pv->save();

                        // attach attribute values if provided (array of ids)
                        if (! empty($v['attribute_value_ids']) && is_array($v['attribute_value_ids'])) {
                            $pv->attributeValues()->sync(array_map('intval', $v['attribute_value_ids']));
                        } elseif (! empty($v['attribute_values']) && is_array($v['attribute_values'])) {
                            // accept array of attribute value objects or values (try id first)
                            $ids = array_map(function ($it) {
                                if (is_array($it) && isset($it['id'])) {
                                    return (int) $it['id'];
                                }

                                return (int) $it;
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

        // If no explicit variants are provided, allow top-level attribute_value_ids to create a default variant
        if ((empty($validated['variants']) || $validated['variants'] === null)
            && array_key_exists('attribute_value_ids', $validated)
            && is_array($validated['attribute_value_ids'])) {
            $attributeValueIds = array_filter(array_map('intval', $validated['attribute_value_ids']));
            if (! empty($attributeValueIds)) {
                $pv = new ProductVariant;
                $pv->product_id = $product->id;
                $pv->sku = substr(preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($product->name ?? 'variant')), 0, 40) . '-' . uniqid();
                if (ProductVariant::where('sku', $pv->sku)->exists()) {
                    $pv->sku .= '-'.uniqid();
                }
                $pv->price = $product->price;
                $pv->discount_price = $product->discount_price;
                $pv->stock = 0;
                $pv->image = $product->image;
                $pv->save();
                $pv->attributeValues()->sync($attributeValueIds);
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
     * Cập nhật sản phẩm, chỉ dành cho admin.
     */
    #[OA\Put(
        path: '/api/products/{id}',
        summary: 'Cập nhật sản phẩm',
        security: [['bearerAuth' => []]],
        tags: ['Sản phẩm'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Áo thun basic cập nhật'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Mô tả đã cập nhật'),
                    new OA\Property(property: 'price', type: 'number', example: 219000),
                    new OA\Property(property: 'discount_price', type: 'number', nullable: true, example: 179000),
                    new OA\Property(property: 'image', type: 'string', nullable: true, example: 'products/ao-thun-cap-nhat.jpg'),
                    new OA\Property(
                        property: 'categories',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2]
                    ),
                    new OA\Property(
                        property: 'variants',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(
                                    property: 'id',
                                    description: 'ID biến thể cần cập nhật. Bỏ qua để tạo biến thể mới.',
                                    type: 'integer',
                                    example: 1
                                ),
                                new OA\Property(property: 'sku', type: 'string', example: 'AO-THUN-DEN-L'),
                                new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, example: 219000),
                                new OA\Property(property: 'discount_price', type: 'number', format: 'float', minimum: 0, nullable: true, example: 189000),
                                new OA\Property(property: 'stock', type: 'integer', minimum: 0, example: 8),
                                new OA\Property(
                                    property: 'image',
                                    description: 'Ảnh riêng của biến thể. Truyền null hoặc chuỗi rỗng để dùng ảnh sản phẩm.',
                                    type: 'string',
                                    nullable: true,
                                    example: 'https://cdn.example.com/products/ao-thun-den.jpg'
                                ),
                                new OA\Property(
                                    property: 'attribute_value_ids',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [1, 3]
                                ),
                            ],
                            type: 'object'
                        )
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật sản phẩm thành công'),
            new OA\Response(response: 403, description: 'Chỉ admin được phép thao tác'),
            new OA\Response(response: 404, description: 'Không tìm thấy sản phẩm'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric',
            'discount_price' => 'nullable|numeric',
            'image' => 'nullable|string',
            'attribute_value_ids' => 'nullable|array',
            'attribute_value_ids.*' => 'integer',
            // flexible inputs
            'categories' => 'nullable',
            'variants' => 'nullable',
            'variants.*.id' => 'sometimes|required|integer|exists:product_variants,id',
            'variants.*.sku' => 'sometimes|required|string|max:255',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.discount_price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|integer|min:0',
            'variants.*.image' => 'nullable|string|max:2048',
            'variants.*.attribute_value_ids' => 'nullable|array',
            'variants.*.attribute_value_ids.*' => 'integer',
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
            if (! is_array($catsRaw)) {
                $cats = [$catsRaw];
            } else {
                $cats = $catsRaw;
            }
            $catIds = array_map(function ($c) {
                if (is_array($c) && isset($c['id'])) {
                    return (int) $c['id'];
                }

                return (int) $c;
            }, $cats);
            $catIds = array_filter($catIds, fn ($v) => $v > 0);
            $product->categories()->sync($catIds);
        }

        // Handle variants: single id / array of ids / array of objects
        if (array_key_exists('variants', $validated) && $validated['variants'] !== null) {
            $varsRaw = $validated['variants'];
            if (! is_array($varsRaw)) {
                $vid = (int) $varsRaw;
                $existing = ProductVariant::find($vid);
                if ($existing) {
                    $existing->product_id = $product->id;
                    $existing->save();
                }
            } else {
                $first = reset($varsRaw);
                if (is_array($first)) {
                    // array of objects => create new variants and assign
                    foreach ($varsRaw as $v) {
                        $pv = null;
                        if (! empty($v['id'])) {
                            $pv = ProductVariant::find((int) $v['id']);
                        }
                        if ($pv) {
                            // update existing
                            if (array_key_exists('sku', $v)) {
                                $pv->sku = $v['sku'] ?? $pv->sku;
                                if ($pv->sku && ProductVariant::where('sku', $pv->sku)->where('id', '!=', $pv->id)->exists()) {
                                    $pv->sku = $pv->sku.'-'.uniqid();
                                }
                            }
                            if (array_key_exists('price', $v)) {
                                $pv->price = $v['price'];
                            }
                            if (array_key_exists('discount_price', $v)) {
                                $pv->discount_price = $v['discount_price'];
                            }
                            if (array_key_exists('stock', $v)) {
                                $pv->stock = $v['stock'];
                            }
                            if (array_key_exists('image', $v)) {
                                $pv->image = ! empty($v['image']) ? $v['image'] : $product->image;
                            }
                            $pv->product_id = $product->id;
                            $pv->save();
                        } else {
                            // create new
                            $pv = new ProductVariant;
                            $pv->sku = $v['sku'] ?? null;
                            if ($pv->sku && ProductVariant::where('sku', $pv->sku)->exists()) {
                                $pv->sku = $pv->sku.'-'.uniqid();
                            }
                            $pv->price = $v['price'] ?? 0;
                            $pv->discount_price = $v['discount_price'] ?? null;
                            $pv->stock = $v['stock'] ?? 0;
                            $pv->image = ! empty($v['image']) ? $v['image'] : $product->image;
                            $pv->product_id = $product->id;
                            $pv->save();
                        }

                        // sync attribute values if provided
                        if (! empty($v['attribute_value_ids']) && is_array($v['attribute_value_ids'])) {
                            $pv->attributeValues()->sync(array_map('intval', $v['attribute_value_ids']));
                        } elseif (! empty($v['attribute_values']) && is_array($v['attribute_values'])) {
                            $ids = array_map(function ($it) {
                                if (is_array($it) && isset($it['id'])) {
                                    return (int) $it['id'];
                                }

                                return (int) $it;
                            }, $v['attribute_values']);
                            $pv->attributeValues()->sync(array_filter($ids));
                        }
                    }
                } else {
                    // array of ids -> make these the product's variants; unassign others
                    $newIds = array_map(fn ($v) => (int) $v, $varsRaw);
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

        if ((empty($validated['variants']) || $validated['variants'] === null)
            && array_key_exists('attribute_value_ids', $validated)
            && is_array($validated['attribute_value_ids'])) {
            $attributeValueIds = array_filter(array_map('intval', $validated['attribute_value_ids']));
            if (! empty($attributeValueIds)) {
                $pv = new ProductVariant;
                $pv->product_id = $product->id;
                $pv->sku = substr(preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($product->name ?? 'variant')), 0, 40) . '-' . uniqid();
                if (ProductVariant::where('sku', $pv->sku)->exists()) {
                    $pv->sku .= '-'.uniqid();
                }
                $pv->price = $product->price;
                $pv->discount_price = $product->discount_price;
                $pv->stock = 0;
                $pv->image = $product->image;
                $pv->save();
                $pv->attributeValues()->sync($attributeValueIds);
            }
        }

        $product->load(['variants', 'categories']);

        $message = null;
        if (! empty($blocked ?? [])) {
            $message = 'Some variants could not be removed because they are referenced by order_details: '.implode(',', $blocked);
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
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => null,
        ], 200);
    }

    public function addFavorite(Request $request, $productId)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'status' => 401,
                'success' => false,
                'message' => 'Unauthorized: login required',
                'data' => null,
            ], 401);
        }

        $product = Product::findOrFail($productId);
        $user->favorites()->firstOrCreate(['product_id' => $product->id]);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Product added to favorites',
            'data' => true,
        ], 200);
    }

    public function removeFavorite(Request $request, $productId)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'status' => 401,
                'success' => false,
                'message' => 'Unauthorized: login required',
                'data' => null,
            ], 401);
        }

        $product = Product::findOrFail($productId);
        $user->favorites()->where('product_id', $product->id)->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Product removed from favorites',
            'data' => true,
        ], 200);
    }

    public function getUserFavorites(Request $request, $userId)
    {
        $user = $request->user();
        if (! $user || $user->id != $userId) {
            return response()->json([
                'status' => 403,
                'success' => false,
                'message' => 'Forbidden: cannot access other user\'s favorites',
                'data' => null,
            ], 403);
        }

        $favorites = $user->favorites()->with('product')->get();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $favorites->map(fn ($fav) => $fav->product)->filter(),
                'pagination' => null,
            ],
        ], 200);
    }
}
