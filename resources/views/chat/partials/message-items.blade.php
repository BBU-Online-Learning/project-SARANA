@foreach($messages as $message)

    @include(
        'chat.partials.message-item',
        [
            'message' => $message,
            'room' => $room
        ]
    )

@endforeach