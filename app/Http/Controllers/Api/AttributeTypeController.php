<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttributeTypeRequest;
use App\Http\Requests\UpdateAttributeTypeRequest;
use App\Models\AttributeType;
use App\Models\AttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttributeTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = AttributeType::with('attributeValues')->get();
        return response()->json($types);
    }

    public function show($id): JsonResponse
    {
        $type = AttributeType::with('attributeValues')->findOrFail($id);
        return response()->json($type);
    }

    public function store(StoreAttributeTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = AttributeType::create(['name' => $data['name']]);

        if (!empty($data['values']) && is_array($data['values'])) {
            foreach ($data['values'] as $value) {
                $type->attributeValues()->create(['value' => $value]);
            }
        }

        return response()->json($type->load('attributeValues'), 201);
    }

    public function update(UpdateAttributeTypeRequest $request, $id): JsonResponse
    {
        $type = AttributeType::findOrFail($id);
        $data = $request->validated();
        $type->update(['name' => $data['name']]);

        if (array_key_exists('values', $data)) {
            // replace existing values with provided list
            $type->attributeValues()->delete();
            if (!empty($data['values']) && is_array($data['values'])) {
                foreach ($data['values'] as $value) {
                    $type->attributeValues()->create(['value' => $value]);
                }
            }
        }

        return response()->json($type->load('attributeValues'));
    }

    public function destroy($id): JsonResponse
    {
        $type = AttributeType::findOrFail($id);
        $type->attributeValues()->delete();
        $type->delete();
        return response()->json(['message' => 'Deleted'], 200);
    }

    /**
     * Return attribute values for a given attribute type id or name
     */
    public function values(Request $request, $idOrName): JsonResponse
    {
        // allow passing numeric id or attribute name
        $type = null;
        if (is_numeric($idOrName)) {
            $type = AttributeType::with('attributeValues')->find($idOrName);
        }
        if (!$type) {
            $type = AttributeType::with('attributeValues')->where('name', $idOrName)->first();
        }
        if (!$type) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['data' => $type->attributeValues->map(function ($v) {
            return ['id' => $v->id, 'value' => $v->value];
        })->values()]);
    }
}
