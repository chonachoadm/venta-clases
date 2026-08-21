@extends('layouts.app')
@section('content')

    <section>
        @if(session('success'))
            <div class="alert alert-success">
                <small>{{session('success')}}</small>
            </div>
        @elseif(session('message'))
            <div class="alert alert-warning">
                <small>{{session('message')}}</small>
            </div>
        @elseif(session('error'))
            <div class="alert alert-danger">
                <small>{{session('error')}}</small>
            </div>
        @endif

        <div>
            <h1>Esta sera la landing page (visitante)</h1>
            <div>
                <a href="{{ route('showLogin') }}">Ya tengo mi cuenta</a>
            </div>
        </div>

        <a href="{{ route('checkout') }}">Adquirir acceso</a>

        <div>
            @foreach($videos as $video)
                <div>
                    <h2>{{ $video->title }}</h2>
                    <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}">
                    <p>{{ Str::limit($video->description, 100) }}</p>
                    <a href="{{ route('details', $video) }}">Ver</a>
                </div>
            @endforeach
        </div>
    </section>

@endsection