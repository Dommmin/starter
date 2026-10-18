<?php

namespace App\Http\Requests\Admin\Import;

use App\Actions\Import\ImportCsv;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload of a CSV to check before importing. Authorization (`import` on the
 * module model) is checked by the controller.
 */
class PreviewImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:'.ImportCsv::MAX_KILOBYTES],
        ];
    }
}
