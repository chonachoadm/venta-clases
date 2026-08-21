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
            <h1>{{ $video->title }}</h1>
            <iframe src="https://www.youtube.com/embed/{{ $video->video_id }}" frameborder="0" allowfullscreen></iframe>
                <div class="row">
                    <div class="col">
                        <h2>Hicieron esta clase</h2>
                        @foreach ($video->viewers as $viewer)
                            <p>{{ $viewer->name }}</p>
                        @endforeach
                    </div>
                    <div class="col">
                        <h2><strong>No</strong> hicieron esta clase</h2>
                        @foreach ($nonViewers as $nonViewer)
                            <p>{{ $nonViewer->name }}</p>
                        @endforeach
                    </div>
                </div>
        </div>
    </section>

@endsection