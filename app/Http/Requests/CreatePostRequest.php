<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
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
            'user_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'price' => 'required|integer',
            'image.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'status' => 'string|max:255',
            'is_premium' => 'integer',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'type' => 'required|string',
            'can_trade_in' => 'required|integer',
            'is_published' => 'integer',
        ];
    }
}
