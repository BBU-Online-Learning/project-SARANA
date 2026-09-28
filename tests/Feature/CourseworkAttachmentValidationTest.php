<?php

use App\Services\CourseworkAttachmentInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

test('coursework inspector accepts matching text and image files', function (): void {
    $inspector = app(CourseworkAttachmentInspector::class);

    $inspector->inspect(UploadedFile::fake()->createWithContent('answer.txt', 'A written answer.'), 0);
    $inspector->inspect(UploadedFile::fake()->image('diagram.png'), 1);

    expect(true)->toBeTrue();
});

test('coursework inspector rejects a file whose extension does not match its content', function (): void {
    $image = UploadedFile::fake()->image('diagram.png');
    $renamed = new UploadedFile($image->getRealPath(), 'diagram.pdf', 'image/png', null, true);

    expect(fn () => app(CourseworkAttachmentInspector::class)->inspect($renamed, 2))
        ->toThrow(ValidationException::class);
});

test('coursework inspector checks the expected Office document structure', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'coursework-document-');

    try {
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::OVERWRITE);
        $archive->addFromString('other.txt', 'Not an Office file');
        $archive->close();

        $invalid = new UploadedFile($path, 'answer.docx', 'application/zip', null, true);
        expect(fn () => app(CourseworkAttachmentInspector::class)->inspect($invalid, 0))
            ->toThrow(ValidationException::class);

        $archive->open($path, ZipArchive::OVERWRITE);
        $archive->addFromString('[Content_Types].xml', '<Types/>');
        $archive->addFromString('word/document.xml', '<document/>');
        $archive->close();

        $valid = new UploadedFile($path, 'answer.docx', 'application/zip', null, true);
        app(CourseworkAttachmentInspector::class)->inspect($valid, 0);
        expect(true)->toBeTrue();
    } finally {
        @unlink($path);
    }
});
