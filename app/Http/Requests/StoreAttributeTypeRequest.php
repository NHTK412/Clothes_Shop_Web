<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeTypeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255|unique:attribute_types,name',
            'values' => 'sometimes|array',
            'values.*' => 'required|string|max:255',
        ];
    }
}
