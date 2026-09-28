<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class CourseworkAttachmentInspector
{
    private const MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'txt' => ['text/plain'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    public function inspect(UploadedFile $file, int $position): void
    {
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = strtolower((string) $file->getMimeType());

        if (! $file->isValid() || ! $path || ! is_readable($path) || $file->getSize() < 1
            || ! isset(self::MIME_TYPES[$extension]) || ! in_array($mimeType, self::MIME_TYPES[$extension], true)) {
            $this->reject($position);
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $image = @getimagesize($path);
            if (! $image || ($image['mime'] ?? null) !== $mimeType) {
                $this->reject($position);
            }
        } elseif ($extension === 'pdf') {
            if (file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
                $this->reject($position);
            }
        } elseif (in_array($extension, ['doc', 'xls', 'ppt'], true)) {
            if (file_get_contents($path, false, null, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
                $this->reject($position);
            }
        } elseif (in_array($extension, ['docx', 'xlsx', 'pptx'], true)) {
            $archive = new ZipArchive;
            if ($archive->open($path) !== true) {
                $this->reject($position);
            }
            $required = match ($extension) {
                'docx' => 'word/document.xml',
                'xlsx' => 'xl/workbook.xml',
                'pptx' => 'ppt/presentation.xml',
            };
            $valid = $archive->locateName('[Content_Types].xml') !== false
                && $archive->locateName($required) !== false;
            $archive->close();
            if (! $valid) {
                $this->reject($position);
            }
        }
    }

    private function reject(int $position): never
    {
        throw ValidationException::withMessages([
            'attachments.'.$position => 'The attachment name and file contents must match a supported file type.',
        ]);
    }
}
