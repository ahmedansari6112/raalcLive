<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class WebContactInsertRequest extends FormRequest
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
            'meta_description' => 'required',
            'schema_code' => 'required',
            'sec_one_heading' => 'required',
            'sec_two_heading' => 'required',
            'sec_two_sub_head_one' => 'required',
            'sec_two_location_one' => 'required',
            'sec_two_phone_one' => 'required',
            'sec_two_email_one' => 'required|email',
            'sec_two_url_one' => 'required',
            'sec_two_sub_head_two' => 'required',
            'sec_two_location_two' => 'required',
            'sec_two_phone_two' => 'required',
            'sec_two_email_two' => 'required|email',
            'sec_two_url_two' => 'required',
            'sec_two_sub_head_three' => 'required',
            'sec_two_location_three' => 'required',
            'sec_two_phone_three' => 'required',
            'sec_two_email_three' => 'required|email',
            'sec_two_url_three' => 'required',
        ];
    }
}
