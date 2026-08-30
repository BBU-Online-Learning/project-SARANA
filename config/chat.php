<?php
// File: D:\education\Laravel_Project\Elearning\config\chat.php
return [
    'allowed_reactions' => ['👍', '🙏', '😁', '❤️', '👎', '😡', '😍'],
    'max_attachment_size_kb' => 20480,
    'max_attachments_per_message' => 10,

    // Voice messages are stored as audio attachments, so audio mime/types must be allowed here.
    'allowed_attachment_extensions' => [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'zip',
        'ogg',
        'oga',
        'webm',
        'mp3',
        'wav',
        'm4a',
        'aac',
        'mpeg',
        'mpga',
        'mp4',
    ],
];