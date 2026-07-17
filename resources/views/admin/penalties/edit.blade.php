@extends('layouts.app')

@section('title', 'Edit Penalty')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Edit Penalty / Warning</h1>
        <a href="{{ route('admin.penalties.index') }}" class="btn btn-outline-secondary btn-sm">Back to Penalties</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.penalties.update', $penalty) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('admin.penalties._form')
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
@endsection
