    <section class="card mb-4" aria-labelledby="class-heading"><div class="card-body">
        <h2 class="h5" id="class-heading">{{ $administrator ? 'Recently updated classes' : ($user->role->name === 'teacher' ? 'Your class spaces' : 'Continue learning') }}</h2>
        <ul class="list-group list-group-flush">
            @forelse($recentClasses as $schoolClass)
                <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                    <a href="{{ route('classes.show', $schoolClass) }}">{{ $schoolClass->name }}</a>
                    <span class="badge {{ $schoolClass->isArchived() ? 'bg-secondary' : 'bg-success' }}">{{ $schoolClass->isArchived() ? 'Archived' : 'Active' }}</span>
                </li>
            @empty
                <li class="list-group-item text-muted">No classes yet. Open Classes to {{ $user->can('manage-classes') ? 'create or join a class' : 'join with a class code' }}.</li>
            @endforelse
        </ul>
        <a class="btn btn-outline-primary mt-3" href="{{ route('classes.index') }}">Open classes</a>
    </div></section>
    <section class="card"><div class="card-body">
        <h2 class="h5">Conversations</h2>
        <p>{{ array_sum($conversationCounts) === 0 ? 'No conversations yet. Start a direct chat or create a group.' : 'Open Chats to continue your direct and group conversations.' }}</p>
        <a class="btn btn-primary" href="{{ route('chat.index') }}">Open chats</a>
    </div></section>
