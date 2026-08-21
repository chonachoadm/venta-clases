<div>
    <h1>Esta es la vista de detalles del video (estudiante)</h1>
</div>


<div>
    <h2>{{ $video->title }}</h2>

    <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}">

    <p>{{ $video->description }}</p>
</div>
    <div class="locked-video">
        <p>🔒 Esta clase es paga. Comprá uno de los packs para tener acceso.</p>
        <a href="{{ route('checkout') }}">Ver packs</a>
    </div>
