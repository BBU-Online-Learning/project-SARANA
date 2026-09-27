<?php

$voiceCallStunUrls = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('VOICE_CALL_STUN_URLS', 'stun:stun.l.google.com:19302')),
)));

$voiceCallIceServers = $voiceCallStunUrls === [] ? [] : [['urls' => $voiceCallStunUrls]];
$voiceCallTurnUrl = trim((string) env('VOICE_CALL_TURN_URL', ''));

if ($voiceCallTurnUrl !== '') {
    $voiceCallIceServers[] = [
        'urls' => array_values(array_filter(array_map('trim', explode(',', $voiceCallTurnUrl)))),
        'username' => (string) env('VOICE_CALL_TURN_USERNAME', ''),
        'credential' => (string) env('VOICE_CALL_TURN_CREDENTIAL', ''),
    ];
}

// File: D:\education\Laravel_Project\Elearning\config\chat.php
return [
    'group_invite_lifetime_days' => 7,
    'group_invite_public_url' => rtrim((string) env('CHAT_GROUP_INVITE_PUBLIC_URL', ''), '/'),
    'voice_calls' => [
        'heartbeat_timeout_seconds' => max(60, (int) env('VOICE_CALL_HEARTBEAT_TIMEOUT', 120)),
        'turn_secret' => (string) env('VOICE_CALL_TURN_SECRET', ''),
        'turn_ttl_seconds' => max(300, (int) env('VOICE_CALL_TURN_TTL', 3600)),
        'ring_timeout_seconds' => (int) env('VOICE_CALL_RING_TIMEOUT', 45),
        'signal_max_bytes' => (int) env('VOICE_CALL_SIGNAL_MAX_BYTES', 32768),
        'ice_servers' => $voiceCallIceServers,
    ],

    'allowed_reactions' => ['👍', '🙏', '😁', '❤️', '👎', '😡', '😍'],
    'max_attachment_size_kb' => (int) env('CHAT_MAX_ATTACHMENT_SIZE_KB', 20480),
    'max_attachment_total_size_kb' => (int) env('CHAT_MAX_ATTACHMENT_TOTAL_SIZE_KB', 51200),
    'max_attachments_per_message' => (int) env('CHAT_MAX_ATTACHMENTS_PER_MESSAGE', 10),

    'stickers' => [
        'star_thumbs_up' => [
            'name' => 'Great job',
            'pack' => 'Study Buddies',
            'asset' => 'images/stickers/star-thumbs-up.webp',
        ],
        'book_hug' => [
            'name' => 'Love learning',
            'pack' => 'Study Buddies',
            'asset' => 'images/stickers/book-hug.webp',
        ],
        'pencil_celebrate' => [
            'name' => 'Well done',
            'pack' => 'Study Buddies',
            'asset' => 'images/stickers/pencil-celebrate.webp',
        ],
        'graduation_cap' => [
            'name' => 'You did it',
            'pack' => 'Study Buddies',
            'asset' => 'images/stickers/graduation-cap.webp',
        ],
        'bbu_ready' => [
            'name' => 'Ready',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/ready.png',
        ],
        'bbu_laugh' => [
            'name' => 'So funny',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/laugh.png',
        ],
        'bbu_wave' => [
            'name' => 'Hello',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/wave.png',
        ],
        'bbu_thumbs_up' => [
            'name' => 'Great',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/thumbs-up.png',
        ],
        'bbu_thank_you' => [
            'name' => 'Thank you',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/thank-you.png',
        ],
        'bbu_thinking' => [
            'name' => 'Thinking',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/thinking.png',
        ],
        'bbu_confused' => [
            'name' => 'Confused',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/confused.png',
        ],
        'bbu_surprised' => [
            'name' => 'Surprised',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/surprised.png',
        ],
        'bbu_sad' => [
            'name' => 'Feeling sad',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/sad.png',
        ],
        'bbu_angry' => [
            'name' => 'Not happy',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/angry.png',
        ],
        'bbu_sorry' => [
            'name' => 'Sorry',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/sorry.png',
        ],
        'bbu_goodbye' => [
            'name' => 'Goodbye',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/goodbye.png',
        ],
        'bbu_studying' => [
            'name' => 'Studying',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/studying.png',
        ],
        'bbu_celebrate' => [
            'name' => 'Yes!',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/celebrate.png',
        ],
        'bbu_love' => [
            'name' => 'Love it',
            'pack' => 'Dev Reactions',
            'asset' => 'images/stickers/bbu-reactions/love.png',
        ],
    ],

    'attachment_mime_types' => [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'oga' => ['audio/ogg', 'application/ogg'],
        'webm' => ['audio/webm', 'video/webm'],
        'mp3' => ['audio/mpeg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        'aac' => ['audio/aac', 'audio/x-hx-aac-adts'],
        'mpeg' => ['audio/mpeg'],
        'mpga' => ['audio/mpeg'],
        'mp4' => ['audio/mp4', 'video/mp4'],
    ],

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
