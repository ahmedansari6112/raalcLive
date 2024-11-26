<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebHomeInsertRequest extends FormRequest
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
            'meta_tag' => 'required|max:255',
            'meta_description'=>'required',
            'header_image'=> 'nullable|image|mimes:jpg,png,jpeg,webp|max:2048',
            'top_right_content'=>'nullable|string',
            'header_one'=>'nullable|string',
            'header_two'=>'nullable|string',
            'sec_two_header_one'=>"nullable|string",
            'sec_two_header_two'=>"nullable|string",
            'sec_two_paragraph'=>"nullable|string",
            'sec_two_image'=>"nullable|image|mimes:jpg,png,jpeg,webp|max:2048",
            'sec_two_name'=>"nullable|string",
            'sec_two_details'=>"nullable|string",
            'sec_three_header_one'=>"nullable|string",
            'sec_three_header_two'=>"nullable|string",
            'sec_three_paragraph'=>"nullable|string",
            'sec_four_header_one'=>"nullable|string",
            'sec_four_header_two'=>"nullable|string",
            'sec_four_paragraph'=>"nullable|string",
            'sec_four_fact_one'=>"required",
            'sec_four_fact_one_title'=>"required",
            'sec_four_fact_two'=>"required",
            'sec_four_fact_two_title'=>"required",
            'sec_four_fact_three'=>"required",
            'sec_four_fact_three_title'=>"required",
            'sec_four_image'=>"nullable|image|mimes:jpg,png,jpeg,webp|max:2048",
        ];
    }
}
