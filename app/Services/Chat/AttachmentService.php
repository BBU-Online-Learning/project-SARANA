<?php

namespace App\Services\Chat;

use App\Models\Attachment;
use App\Models\Message;
use App\Rules\SafeChatAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AttachmentService
{
    /**
     * Store all uploaded attachments for a message.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Attachment>
     */
    public function store(Message $message, array $files): Collection
    {
        abort_unless(Auth::user()?->status === 'active'
            && $message->room?->members()->where('users.id', Auth::id())->exists(), 403);
        Validator::make(['attachments' => $files], SafeChatAttachment::rules())->validate();

        return DB::transaction(function () use ($message, $files) {

            $attachments = collect();

            foreach ($files as $file) {

                $attachment = Attachment::create([
                    'message_id' => $message->id,
                    'room_id' => $message->room_id,
                    'uploaded_by' => Auth::id(),

                    'original_name' => SafeChatAttachment::filename($file->getClientOriginalName(), $file->getClientOriginalExtension()),

                    /*
                     | These two fields will be updated after MediaLibrary
                     | stores the physical file.
                     */
                    'storage_path' => '',
                    'extension' => strtolower($file->getClientOriginalExtension()),

                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Store file using Spatie MediaLibrary
                |--------------------------------------------------------------------------
                */

                $media = $attachment
                    ->addMedia($file)
                    ->usingName(pathinfo($attachment->original_name, PATHINFO_FILENAME))
                    ->usingFileName(
                        Str::uuid().'.'.strtolower($file->getClientOriginalExtension())
                    )
                    ->storingConversionsOnDisk('chat_private')
                    ->toMediaCollection('attachment', 'chat_private');

                /*
                |--------------------------------------------------------------------------
                | Keep legacy columns synchronized
                |--------------------------------------------------------------------------
                */

                $attachment->update([
                    'storage_path' => $media->getPathRelativeToRoot(),
                    'extension' => strtolower($media->extension),
                ]);

                $attachments->push($attachment);
            }

            return $attachments;
        });
    }

    /**
     * Delete every attachment belonging to a message.
     */
    public function delete(Message $message): void
    {
        foreach ($message->attachments as $attachment) {

            $attachment->clearMediaCollection('attachment');

            $attachment->delete();
        }
    }

    /**
     * Replace attachments.
     *
     * Can be used later for message editing.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function replace(Message $message, array $files): Collection
    {
        $this->delete($message);

        return $this->store($message, $files);
    }
}
