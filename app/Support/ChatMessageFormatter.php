<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class ChatMessageFormatter
{
    public static function linkify(?string $message): HtmlString
    {
        $escapedMessage = e($message ?? '');
        $linkedMessage = preg_replace_callback(
            '~https?://[^\s<]+~iu',
            function (array $matches): string {
                $matchedUrl = $matches[0];
                $url = rtrim($matchedUrl, '.,!?;:)');
                $trailingCharacters = substr($matchedUrl, strlen($url));
                $decodedUrl = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $path = parse_url($decodedUrl, PHP_URL_PATH) ?: '';
                $isGroupInvite = preg_match('~^/chat/invites/\d+$~', $path) === 1;
                $query = parse_url($decodedUrl, PHP_URL_QUERY);
                $inviteHref = $path.(is_string($query) && $query !== '' ? '?'.$query : '');
                $href = $isGroupInvite ? e($inviteHref) : $url;
                $label = $isGroupInvite
                    ? '<i class="ti ti-users-plus" aria-hidden="true"></i><span>Join group chat</span>'
                    : $url;
                $class = $isGroupInvite ? 'chat-message-link is-group-invite' : 'chat-message-link';

                return '<a class="'.$class.'" href="'.$href.'" target="_blank" rel="noopener noreferrer">'.$label.'</a>'.$trailingCharacters;
            },
            $escapedMessage,
        );

        return new HtmlString($linkedMessage ?? $escapedMessage);
    }
}
