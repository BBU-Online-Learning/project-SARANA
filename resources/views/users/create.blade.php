@extends('layouts.app')
@section('content')
    <div class="page-container">


        <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column gap-2">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold mb-0">Add User</h4>
            </div>

            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">home</a></li>

                    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">list_user</a></li>

                    <li class="breadcrumb-item active">Add User</li>
                </ol>
            </div>
        </div>




        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">

                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif
                        <form action="{{ route('users.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3">

                                {{-- Name (if you still want name, keep it; otherwise remove this block) --}}
                                <div class="col-md-6">
                                    <label class="form-label">User Name</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Email --}}
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                                        name="email" value="{{ old('email') }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Role --}}

                                <div class="col-md-6">
                                    <label class="form-label">Role</label>
                                    <select name="role_id" class="form-select @error('role_id') is-invalid @enderror">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}"
                                                {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>                                    
                                    @error('role_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                {{-- phone --}}
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                        name="phone" value="{{ old('phone') }}" required>
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>


                                {{-- Password --}}
                                <div class="col-md-6">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror"
                                        name="password" required>
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                {{-- Profile Image --}}
                                <div class="col-md-6">
                                    <label class="form-label">Profile Image</label>
                                    <input type="file" class="form-control" name="profile"
                                        onchange="previewProfileImage(event)">

                                    {{-- Preview --}}
                                    <div class="mt-2">
                                        <img id="profilePreview" src="{{ asset('images/avatar.png') }}" alt="Preview"
                                            class="img-thumbnail" style="width:120px; height:120px; object-fit:cover;">
                                    </div>
                                </div>

                                {{-- Confirm Password --}}
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" name="password_confirmation" required>
                                </div>


                                {{-- Status --}}
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status1" class="form-label">Status</label>
                                        <select name="status" id="status1" class="form-select" required>
                                            <option value="">Choose a status</option>
                                            @foreach (['active', 'inactive', 'suspended'] as $status)
                                                <option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                        @error('status')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-12 text-end pt-3">
                                    <button type="submit" class="btn btn-primary">
                                        Save User
                                    </button>
                                    <a href="{{ route('users.index') }}" class="btn btn-light">
                                        Cancel
                                    </a>
                                </div>

                            </div>
                        </form>

                    </div>
                </div>
            </div>

        </div>

    </div> <!-- container -->

    <script>
        function previewProfileImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('profilePreview');

            if (!file) return;

            if (!file.type.startsWith('image/')) {
                alert('Please select an image file');
                event.target.value = '';
                return;
            }

            preview.src = URL.createObjectURL(file);
        }
    </script>
@endsection
