@extends('layouts.app')

@section('title', 'Upload Warning Letter')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Upload Warning Letter</h1>
        <a href="{{ route('admin.warning-letters.index') }}" class="btn btn-outline-secondary btn-sm">Back to Warning Letters</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.warning-letters.store') }}" enctype="multipart/form-data">
                @csrf
                @include('admin.warning-letters._form', ['warningLetter' => null])
                <button type="submit" class="btn btn-primary">Upload Warning Letter</button>
            </form>
        </div>
    </div>
@endsection
