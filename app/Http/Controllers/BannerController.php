<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => Banner::query()
                    ->latest()
                    ->get()
                    ->map(fn (Banner $banner) => $this->format($banner))
                    ->values(),
                'pagination' => null,
            ],
        ]);
    }

    public function show(Banner $banner)
    {
        return $this->itemResponse($banner);
    }

    public function store(Request $request)
    {
        $banner = Banner::create($this->validateBanner($request));

        return $this->itemResponse($banner, 201);
    }

    public function update(Request $request, Banner $banner)
    {
        $banner->update($this->validateBanner($request, true));

        return $this->itemResponse($banner->fresh());
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Đã xóa banner thành công.',
            'data' => null,
        ]);
    }

    private function validateBanner(Request $request, bool $updating = false): array
    {
        $presence = $updating ? 'sometimes' : 'required';
        $descriptionPresence = $updating ? 'sometimes|nullable' : 'required';

        return $request->validate([
            'label' => "{$presence}|string|max:255",
            'title' => "{$presence}|string|max:500",
            'description' => "{$descriptionPresence}|string|max:5000",
            'image_url' => "{$presence}|url|max:2048",
        ]);
    }

    private function itemResponse(Banner $banner, int $status = 200)
    {
        return response()->json([
            'status' => $status,
            'success' => true,
            'message' => null,
            'data' => $this->format($banner),
        ], $status);
    }

    private function format(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'label' => $banner->label,
            'title' => $banner->title,
            'description' => $banner->description,
            'image_url' => $banner->image_url,
            'created_at' => $banner->created_at?->toISOString(),
            'updated_at' => $banner->updated_at?->toISOString(),
        ];
    }
}
