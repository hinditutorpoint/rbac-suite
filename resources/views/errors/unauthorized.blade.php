@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            Access Denied
                        </h4>
                    </div>

                    <div class="card-body">
                        <div class="text-center py-4">
                            <div class="mb-4">
                                <i class="fas fa-lock fa-5x text-danger"></i>
                            </div>

                            <h5 class="text-muted">{{ $message }}</h5>

                            @if (!empty($required))
                                <p class="mt-3">
                                    <strong>Required {{ ucfirst($type) }}(s):</strong>
                                    @foreach ($required as $item)
                                        <span class="badge bg-secondary">{{ $item }}</span>
                                    @endforeach
                                </p>
                            @endif

                            <div class="mt-4">
                                <a href="{{ url()->previous() }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Go Back
                                </a>
                                <a href="{{ route('home') }}" class="btn btn-primary">
                                    <i class="fas fa-home"></i> Home
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
