<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
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
            'post_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'required|integer',
            'is_premium' => 'required|integer',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ];
    }
}
