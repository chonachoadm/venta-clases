<div>
    <h1>Esta es la vista de detalles del video (Usuario)</h1>
    <h2>Bienvenidx {{ auth()->user()->name }}</h2>
</div>

<div>
    <a href="{{ route('videos') }}">Todos los videos</a>
</div>


<div>
    <h2>{{ $video->title }}</h2>

    <iframe src="https://www.youtube.com/embed/{{ $video->video_id }}" frameborder="0" allowfullscreen></iframe>

    <p>{{ $video->description }}</p>
    <form action="{{ route('videos.watched', $video->id) }}" method="post">
        @csrf
        <div>
            <label for="watched">Clase hecha</label>
            <input type="checkbox" name="watched" id="watched" {{ $watched ? 'checked' : '' }}
            onchange="this.form.submit()">
        </div>
    </form>
</div>