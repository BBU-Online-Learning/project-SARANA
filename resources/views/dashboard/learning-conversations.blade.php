<section class="learning-conversation-callout" aria-labelledby="learning-conversations">
    <i class="ti ti-messages" aria-hidden="true"></i>
    <div><h2 id="learning-conversations">Keep the conversation going</h2><p>{{ array_sum($conversationCounts) === 0 ? 'No conversations yet. Connect with your teachers and classmates.' : 'Continue your direct and group conversations.' }}</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('chat.index') }}">View chats</a>
</section>
