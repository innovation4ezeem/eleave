@extends('layouts.app')

@section('title', 'Add Penalty')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Add Penalty / Warning</h1>
        <a href="{{ route('admin.penalties.index') }}" class="btn btn-outline-secondary btn-sm">Back to Penalties</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.penalties.store') }}" enctype="multipart/form-data">
                @csrf
                @include('admin.penalties._form', ['penalty' => null])
                <button type="submit" class="btn btn-primary">Save Penalty</button>
            </form>
        </div>
    </div>
@endsection
