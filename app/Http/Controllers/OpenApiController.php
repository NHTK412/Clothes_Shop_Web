<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API Cửa hàng quần áo',
    description: 'Tài liệu API cho hệ thống cửa hàng quần áo'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'Nhập JWT token theo dạng Bearer token.',
    bearerFormat: 'JWT',
    scheme: 'bearer'
)]
class OpenApiController
{
    #[OA\Get(
        path: '/api/auth/reset-password/{token}',
        summary: 'Mở trang đặt lại mật khẩu',
        tags: ['Xác thực'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'email', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'email'), example: 'user@example.com'),
        ],
        responses: [
            new OA\Response(response: 302, description: 'Chuyển hướng đến trang đặt lại mật khẩu ở frontend'),
        ]
    )]
    public function resetPasswordRedirectDocumentation(): void
    {
    }
}
