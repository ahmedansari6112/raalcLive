<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "meta_tag" => "required|max:255",
            "meta_description" => "required",
            "title" => "required",
            "images.*" => 'nullable|image|mimes:jpg,png,jpeg,webp,svg',
            "author_name" => "required",
            "date" => "required|date"
        ];
    }
}
