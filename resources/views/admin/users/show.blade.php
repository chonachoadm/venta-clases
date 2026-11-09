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
                <p><strong>Clases vistas: </strong>{{ $user->watchedVideos()->whereNotNull('watched_at')->count() }} de {{ $totalVideos }} ({{ $user->watchedPercentage($totalVideos) }}%)</p>
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
                <p><strong>Plan adquirido: </strong>{{ $activeSubscription->package?->name }} (suscripción)</p>
            </li>
            <li>
                <p><strong>Fecha de compra: </strong>{{ $activeSubscription->created_at->format('d/m/Y') }}</p>
            </li>
            <li>
                <p><strong>Fecha del siguiente pago: </strong>{{ $activeSubscription->expires_at->format('d/m/Y') }}</p>
            </li>
            @endif
            @if($activePurchase)
            <li>
                <p><strong>Plan adquirido: </strong>{{ $activePurchase->package?->name }} (pago único)</p>
            </li>
            <li>
                <p><strong>Fecha de compra: </strong>{{ $activePurchase->created_at->format('d/m/Y') }}</p>
            </li>
            <li>
                <p><strong>Acceso hasta: </strong>{{ $activePurchase->expires_at->format('d/m/Y') }}</p>
            </li>
            @endif
            @unless($activeSubscription || $activePurchase)
            <li>
                <p><em>Este usuario no tiene planes activos</em></p>
            </li>
            @endunless
        </ul>
        <div class="row">
            <h4>Contactar</h4>
            <div class="col-2">
                <a href="https://wa.me/{{ $phoneNumber }}">
                    <img class="w-25" src="{{ asset('/storage/images/icons/wpp-icon.svg') }}" alt="">
                </a>
            </div>
        </div>
    </div>
</section>

@endsection