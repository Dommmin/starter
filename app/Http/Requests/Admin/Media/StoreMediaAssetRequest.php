<?php

namespace App\Http\Requests\Admin\Media;

use App\Models\MediaAsset;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * One DAM upload. The type is decided by the file content (fileinfo), the
 * client extension must belong to that type, images must decode with a
 * matching type and a bounded pixel count, and PDFs must carry the PDF
 * signature. Everything else is rejected before touching storage.
 */
class StoreMediaAssetRequest extends FormRequest
{
    /**
     * The route checks `create`; the request repeats it so it stays safe
     * when reused elsewhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', MediaAsset::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var array<string, list<string>> $allowedTypes */
        $allowedTypes = config('media.allowed_types');

        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('media.max_upload_kb'),
                'mimetypes:'.implode(',', array_keys($allowedTypes)),
            ],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('file')) {
                    return;
                }

                $file = $this->uploadedFile();
                $mime = $this->detectedMime();

                /** @var array<string, list<string>> $allowedTypes */
                $allowedTypes = config('media.allowed_types');
                $extension = strtolower($file->getClientOriginalExtension());

                if (! in_array($extension, $allowedTypes[$mime] ?? [], true)) {
                    $validator->errors()->add('file', __('admin.media.validation.extensionMismatch'));

                    return;
                }

                if (! $this->contentMatches($file, $mime)) {
                    $validator->errors()->add('file', __('admin.media.validation.unreadable'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('admin.media.validation.required'),
            'file.file' => __('admin.media.validation.required'),
            'file.uploaded' => __('admin.media.validation.tooLarge', ['max' => $this->maxMegabytes()]),
            'file.max' => __('admin.media.validation.tooLarge', ['max' => $this->maxMegabytes()]),
            'file.mimetypes' => __('admin.media.validation.type'),
        ];
    }

    public function uploadedFile(): UploadedFile
    {
        $file = $this->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }

    /**
     * MIME type sniffed from the content (never the client header).
     */
    public function detectedMime(): string
    {
        return (string) $this->uploadedFile()->getMimeType();
    }

    /**
     * Client file name reduced to a safe display label: no path, control or
     * markup characters, bounded length, extension of the detected type.
     */
    public function sanitizedOriginalName(): string
    {
        $file = $this->uploadedFile();
        $name = pathinfo(basename(str_replace('\\', '/', $file->getClientOriginalName())), PATHINFO_FILENAME);
        $name = (string) preg_replace('/[^\pL\pN._ -]+/u', '-', $name);
        $name = trim((string) preg_replace('/-{2,}/', '-', $name), ' .-_');
        $name = mb_substr($name === '' ? 'file' : $name, 0, 150);

        return $name.'.'.strtolower($file->getClientOriginalExtension());
    }

    private function contentMatches(UploadedFile $file, string $mime): bool
    {
        $path = (string) $file->getRealPath();

        if ($mime === 'application/pdf') {
            $handle = fopen($path, 'rb');
            $header = $handle === false ? '' : (string) fread($handle, 5);

            if ($handle !== false) {
                fclose($handle);
            }

            return $header === '%PDF-';
        }

        $info = @getimagesize($path);

        return $info !== false
            && $info['mime'] === $mime
            && $info[0] > 0
            && $info[1] > 0
            && (int) config('media.max_pixels') >= $info[0] * $info[1];
    }

    private function maxMegabytes(): int
    {
        return intdiv((int) config('media.max_upload_kb'), 1024);
    }
}
