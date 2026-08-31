<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Rules\SafeChatAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AttachmentController extends Controller
{
    public function show(Attachment $attachment): BinaryFileResponse
    {
        return $this->deliver($attachment);
    }

    public function thumbnail(Attachment $attachment): BinaryFileResponse
    {
        return $this->deliver($attachment, thumbnail: true);
    }

    public function download(Attachment $attachment): BinaryFileResponse
    {
        return $this->deliver($attachment, download: true);
    }

    private function deliver(Attachment $attachment, bool $thumbnail = false, bool $download = false): BinaryFileResponse
    {
        Gate::authorize('view', $attachment);
        $media = $attachment->getFirstMedia('attachment');
        abort_unless($media, 404);
        abort_if(str_contains($media->file_name, '/') || str_contains($media->file_name, '\\'), 404);
        abort_if($thumbnail && ! $attachment->isImage(), 404);

        $conversion = $thumbnail && $media->hasGeneratedConversion('thumb') ? 'thumb' : '';
        $diskName = $conversion ? ($media->conversions_disk ?: $media->disk) : $media->disk;
        abort_unless(in_array($diskName, ['chat_private', 'public'], true), 404);
        $disk = Storage::disk($diskName);
        $root = realpath($disk->path(''));
        $path = realpath($disk->path($media->getPathRelativeToRoot($conversion)));
        abort_unless($root && $path && is_file($path)
            && str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR), 404);

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        $inline = in_array($mime, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'audio/mpeg', 'audio/ogg', 'application/ogg', 'audio/wav', 'audio/x-wav',
            'audio/webm', 'video/webm', 'audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/aac',
            'audio/vnd.wave', 'audio/x-hx-aac-adts',
        ], true);
        $filename = SafeChatAttachment::filename($attachment->original_name, $conversion ? 'jpg' : ($attachment->extension ?? 'bin'));

        $response = response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Referrer-Policy' => 'no-referrer',
        ]);
        $response->setContentDisposition(
            $download || ! $inline ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $filename
        );
        $response->setPrivate();

        return $response;
    }
}
