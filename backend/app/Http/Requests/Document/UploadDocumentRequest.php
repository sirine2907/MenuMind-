<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'extensions:txt,md,pdf', 'mimes:txt,md,pdf'],
            'title' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Only .txt, .md and .pdf documents are supported.',
            'file.mimes' => 'Only .txt, .md and .pdf documents are supported.',
        ];
    }
}
