                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Class Info</h5>

                        @can('manageMembers', $schoolClass)
                        <div class="mb-2">
                            <strong>Join Code:</strong>
                            <span class="badge bg-dark">{{ $schoolClass->join_code }}</span>
                        </div>
                        @endcan

                        <div class="mb-2">
                            <strong>Created By:</strong>
                            <span>{{ $schoolClass->creator?->name ?? 'Unknown' }}</span>
                        </div>

                        <div class="mb-2">
                            <strong>Members:</strong>
                            <span>{{ $schoolClass->members->count() }}</span>
                        </div>
                    </div>
                </div>

