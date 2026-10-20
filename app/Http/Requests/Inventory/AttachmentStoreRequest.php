<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class AttachmentStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:receipt,acknowledgment,maintenance,inspection,transfer,disposal,other'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])->max(10 * 1024)],
        ];
    }
}
