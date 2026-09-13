    <div class="row g-3 mb-4" aria-label="Workspace counts">
        <div class="col-6 col-lg-3"><a class="card workspace-count" href="{{ route('classes.index') }}"><div class="card-body"><span>{{ $administrator ? 'Active institution classes' : 'My active classes' }}</span><strong data-count="active-classes">{{ $classCounts['active'] }}</strong></div></a></div>
        <div class="col-6 col-lg-3"><a class="card workspace-count" href="{{ route('classes.index') }}"><div class="card-body"><span>{{ $administrator ? 'Archived institution classes' : 'My archived classes' }}</span><strong data-count="archived-classes">{{ $classCounts['archived'] }}</strong></div></a></div>
        <div class="col-6 col-lg-3"><a class="card workspace-count" href="{{ route('chat.index') }}"><div class="card-body"><span>My direct conversations</span><strong data-count="direct-rooms">{{ $conversationCounts['direct'] }}</strong></div></a></div>
        <div class="col-6 col-lg-3"><a class="card workspace-count" href="{{ route('chat.index') }}"><div class="card-body"><span>My group conversations</span><strong data-count="group-rooms">{{ $conversationCounts['group'] }}</strong></div></a></div>
    </div>
