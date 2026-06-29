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
            'name' => 'sometimes|required|string|max:255|unique:attribute_types,name,'.$id,
            'display_name' => 'sometimes|required|string|max:255',
        ];
    }
}
