    @can('access-admin')
        <section class="card mb-4" aria-labelledby="account-heading"><div class="card-body">
            <h2 class="h5" id="account-heading">{{ $user->role->name === 'super_admin' ? 'Privileged account administration' : 'Teacher and student administration' }}</h2>
            <p class="text-muted">Current accounts you may manage, including suspended accounts. Deleted accounts are excluded.</p>
            <dl class="row">
                @foreach($accountCounts as $role => $count)
                    <div class="col-sm-4"><dt>{{ ucfirst($role) }} accounts</dt><dd class="h3" data-count="{{ $role }}-accounts">{{ $count }}</dd></div>
                @endforeach
            </dl>
            <a class="btn btn-primary" href="{{ route('users.index') }}">Manage accounts</a>
            <a class="btn btn-outline-secondary" href="{{ route('classes.index') }}">Class administration</a>
        </div></section>
    @endcan
