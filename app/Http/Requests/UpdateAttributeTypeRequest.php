<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttributeTypeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        return [
            'name' => 'required|string|max:255|unique:attribute_types,name,' . $id,
            'values' => 'sometimes|array',
            'values.*' => 'required|string|max:255',
        ];
    }
}
