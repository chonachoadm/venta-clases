@extends('layouts.app')
@section('content')

<section>
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
        <div>
            <a href="{{ route('usuarios.index') }}">Volver</a>
        </div>
        <h1>{{ $user->name }}</h1>
    </div>
    <div>
        <h4>
            Información
        </h4>
        <ul>
            <li>
                <p><strong>Email: </strong>{{ $user->email }}</p>
            </li>
            <li>
                <p><strong>Teléfono: </strong>{{ $user->phone }}</p>
            </li>
            <li>
                <p><strong>Clases vistas: </strong>{{ $user->watchedVideos()->whereNotNull('watched_at')->count() }} de {{ $totalVideos }} ({{ round($user->watchedVideos()->whereNotNull('watched_at')->count() / $totalVideos * 100)}}%)</p>
                <div class="d-flex">
                    @foreach ($user->watchedVideos as $video)
                    <div>
                        <img class="w-50 img-fluid" src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}">
                        <p>{{ $video->title }}</p>
                    </div>
                    @endforeach
                </div>
            </li>
        </ul>
        <h4>
            Plan de entrenamiento
        </h4>
        <ul>
            @if($activeSubscription)
            <li>
                <p><strong>Fecha de compra: </strong>{{ $activeSubscription->created_at->format('d/m/Y') }}</p>
            </li>
            <li>
                <p><strong>Fecha de vencimiento: </strong>{{ $activeSubscription->expires_at->format('d/m/Y') }}</p>
            </li>
            @else
            <li>
                <p><em>Este usuario no tiene planes activos</em></p>
            </li>
            @endif
        </ul>
    </div>
</section>

@endsection