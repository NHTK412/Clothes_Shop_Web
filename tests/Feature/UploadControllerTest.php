<?php

namespace Tests\Feature;

use App\Http\Controllers\UploadController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    public function test_upload_returns_a_clear_error_when_cloudinary_is_not_configured(): void
    {
        $uploadedFile = UploadedFile::fake()->image('test.png', 600, 600);
        $request = Request::create('/api/upload', 'POST', [], [], [], ['CONTENT_TYPE' => 'multipart/form-data']);
        $request->files->set('image', $uploadedFile);
        config(['services.cloudinary' => ['cloud_name' => '', 'api_key' => '', 'api_secret' => '']]);

        $controller = new UploadController();
        $response = $controller->uploadProductImage($request);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('success', $response->getContent());
        $this->assertStringContainsString('Cloudinary', $response->getContent());
        $this->assertStringContainsString('CLOUDINARY_', $response->getContent());
    }
}
