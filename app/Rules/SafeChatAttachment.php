<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use ZipArchive;

class SafeChatAttachment implements ValidationRule
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'attachments' => [
                'nullable',
                'array',
                'max:'.config('chat.max_attachments_per_message'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $totalBytes = array_sum(array_map(
                        fn (mixed $file): int => $file instanceof UploadedFile ? (int) $file->getSize() : 0,
                        $value,
                    ));
                    $maximumBytes = (int) config('chat.max_attachment_total_size_kb') * 1024;

                    if ($totalBytes > $maximumBytes) {
                        $maximumMegabytes = round($maximumBytes / 1048576);
                        $fail("The combined attachment size must not exceed {$maximumMegabytes} MB.");
                    }
                },
            ],
            'attachments.*' => ['bail', 'required', 'file', 'max:'.config('chat.max_attachment_size_kb'), new self],
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The attachment could not be uploaded.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $mime = $value->getMimeType();
        $parts = explode('.', strtolower($value->getClientOriginalName()));
        if (! in_array($extension, config('chat.allowed_attachment_extensions'), true)
            || array_intersect($parts, FileAdder::$defaultDisallowedExtensions) !== []
            || ! in_array($mime, config('chat.attachment_mime_types.'.$extension, []), true)) {
            $fail('This attachment type or filename is not allowed.');

            return;
        }

        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($value->getPathname());
            if (! $dimensions || $dimensions[0] * $dimensions[1] > 16000000) {
                $fail('Images must be valid raster images no larger than 16 megapixels.');
            }
        }

        if (in_array($extension, ['docx', 'xlsx'], true) && ! $this->isOfficeDocument($value, $extension)) {
            $fail('The attachment must be a valid DOCX or XLSX document.');
        }
    }

    private function isOfficeDocument(UploadedFile $file, string $extension): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getPathname(), ZipArchive::RDONLY) !== true) {
            return false;
        }

        try {
            $entry = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
            $contentType = $extension === 'docx'
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml';
            $types = $zip->getFromName('[Content_Types].xml', 65536);

            return $zip->locateName($entry) !== false && is_string($types) && str_contains($types, $contentType);
        } finally {
            $zip->close();
        }
    }

    public static function filename(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $stem = Str::ascii(pathinfo($name, PATHINFO_FILENAME));
        $stem = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $stem), '-_');
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower($extension));
        if (! in_array($extension, config('chat.allowed_attachment_extensions'), true)) {
            $extension = 'bin';
        }

        return (substr($stem, 0, 100) ?: 'attachment').'.'.($extension ?: 'bin');
    }
}
