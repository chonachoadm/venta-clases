@extends('layouts.app')
@section('content')

@if(session('success'))
<div class="alert alert-success">
    <small>{{session('success')}}</small>
</div>
@endif
@if(session('message'))
<div class="alert alert-warning">
    <small>{{session('message')}}</small>
</div>
@endif

<div>
    <h1>Esta sera la pagina de los videos ({{auth()->user()->name}})</h1>
    <form action="{{ route('logout') }}" method="POST">
    @csrf
        <button type="submit">Cerrar sesión</button>
    </form>
</div>


<div>
    @foreach($videos as $video)
    <div>
        <h2>{{ $video->title }}</h2>
        <img src="{{ $video->thumbnail_url }}" alt="$video->title">
        <a href="{{ route('selectedVideo', $video) }}">Ver</a>
        @if(in_array($video->id, $watchedVideoIds))
        <p><em>Clase vista</em></p>
        @endif
    </div>
    @endforeach
</div>

@endsection