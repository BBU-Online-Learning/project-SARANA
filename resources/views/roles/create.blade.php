@extends('layouts.app')
@section('content')
    <div class="page-container">


        <div class="page-title-head d-flex align-items-sm-center flex-sm-row flex-column gap-2">
            <div class="flex-grow-1">
                <h4 class="fs-18 fw-semibold mb-0">Add Role</h4>
            </div>
            <div class="text-end">
                <ol class="breadcrumb m-0 py-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>

                    <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">list</a></li>

                    <li class="breadcrumb-item active">Add</li>
                </ol>
            </div>
            
        </div>




        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">

                    <div class="card-body">
                        <form action="{{ route('roles.store') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="mb-3">
                                        <label for="" class="form-label"> Name</label>
                                        <input type="text" class="form-control" name="name" placeholder="Enter Role name"
                                            required="" {{ old('name') }}>
                                    </div>
                                </div>

                                <div class="col-lg-12">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="7"  {{ old('description') }}></textarea>
                                    </div>
                                </div>
                                 <div class="col-lg-12">
                                    <div class="mb-3">
                                        <input name="status" value="0" type="hidden">
                                        <input name="status" id="status1" value="1" type="checkbox" class="form-check-input" {{ old('status') }} checked >
                                        <label for="status1" class="form-label">Active</label>
                                        
                                    </div>
                                </div>
                                <div class="card-footer border-top border-dashed text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </div>

                            </div>
                        </form>

                    </div>
                </div>
            </div>

        </div>

    </div> <!-- container -->
@endsection
