<?php

namespace App\Http\Controllers;

use Cloudinary\Cloudinary;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Throwable;

class UploadController extends Controller
{
    #[OA\Post(
        path: '/api/upload',
        operationId: 'uploadProductImage',
        summary: 'Tải ảnh sản phẩm lên Cloudinary',
        description: 'Tải một ảnh sản phẩm lên Cloudinary và trả về image_url cùng public_id. Endpoint yêu cầu đăng nhập.',
        security: [['bearerAuth' => []]],
        tags: ['Upload'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['image'],
                    properties: [
                        new OA\Property(
                            property: 'image',
                            description: 'File ảnh sản phẩm. Hỗ trợ jpg, jpeg, png, webp. Tối đa 2MB.',
                            type: 'string',
                            format: 'binary'
                        ),
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tải ảnh thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'image_url', type: 'string', example: 'https://res.cloudinary.com/demo/image/upload/v1710000000/clothes_shop/products/abc.jpg'),
                                new OA\Property(property: 'public_id', type: 'string', example: 'clothes_shop/products/abc'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Chưa xác thực',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 401),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'File ảnh không hợp lệ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 422),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function uploadProductImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp',
        ]);

        $cloudinaryConfig = config('services.cloudinary', []);
        $cloudName = trim((string) ($cloudinaryConfig['cloud_name'] ?? ''));
        $apiKey = trim((string) ($cloudinaryConfig['api_key'] ?? ''));
        $apiSecret = trim((string) ($cloudinaryConfig['api_secret'] ?? ''));

        if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Cloudinary chưa được cấu hình. Vui lòng điền CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY và CLOUDINARY_API_SECRET vào file .env.',
                'data' => null,
            ], 500);
        }

        try {
            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => [
                    'secure' => (bool) ($cloudinaryConfig['secure'] ?? true),
                ],
            ]);

            $result = $cloudinary->uploadApi()->upload(
                $request->file('image')->getRealPath(),
                [
                    'folder' => 'clothes_shop/products',
                ]
            );

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => [
                    'image_url' => $result['secure_url'],
                    'public_id' => $result['public_id'],
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Không thể upload ảnh lên Cloudinary: ' . $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}
