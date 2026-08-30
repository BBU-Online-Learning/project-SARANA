<div class="modal fade" id="createChatModal">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">
                <h5>Create Chat</h5>
            </div>

            <div class="modal-body">

                <ul class="nav nav-tabs mb-3">

                    <li class="nav-item">
                        <button
                            class="nav-link active"
                            data-bs-toggle="tab"
                            data-bs-target="#direct-tab">
                            Direct Chat
                        </button>
                    </li>

                    <li class="nav-item">
                        <button
                            class="nav-link"
                            data-bs-toggle="tab"
                            data-bs-target="#group-tab">
                            Group Chat
                        </button>
                    </li>

                </ul>

                <div class="tab-content">

                    {{-- DIRECT --}}
                    <div class="tab-pane fade show active" id="direct-tab">

                        <select 
                            id="direct-user-id"
                            class="form-select">

                            <option value="">
                                Select User
                            </option>

                            @foreach($users as $user)

                                @if($user->id !== auth()->id())

                                    <option value="{{ $user->id }}">
                                        {{ $user->name }}
                                    </option>

                                @endif

                            @endforeach

                        </select>

                        <button
                            id="create-direct-btn"
                            class="btn btn-primary mt-3 w-100">

                            Create Direct Chat

                        </button>

                    </div>

                    {{-- GROUP --}}
                    <div class="tab-pane fade" id="group-tab">

                        <input
                            type="text"
                            id="group-name"
                            class="form-control mb-3"
                            placeholder="Group Name">

                        <select
                            multiple
                            id="group-members"
                            class="form-select">

                            @foreach($users as $user)

                                @if($user->id !== auth()->id())

                                    <option value="{{ $user->id }}">
                                        {{ $user->name }}
                                    </option>

                                @endif

                            @endforeach

                        </select>

                        <button
                            id="create-group-btn"
                            class="btn btn-success mt-3 w-100">

                            Create Group

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>