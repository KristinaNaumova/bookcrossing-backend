<?php

namespace App\Modules\Ad\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'book_name' => 'required|string',
            'book_author' => 'required|string',
            'description' => 'nullable|string',
            'comment' => 'nullable|string',
            'type' => 'in:Gift,Exchange,Rent',
            'genres' => 'required|array',
            'genres.*' => Rule::exists('genres', 'id'),
            'deadline' => 'nullable|integer',
        ];
    }
}

