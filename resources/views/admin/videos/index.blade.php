@extends('layouts.app')
@section('content')

<section class="py-4">
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

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="mb-0">Clases</h1>
        <a href="{{ route('dashboard') }}">Volver al panel</a>
    </div>

    <form action="{{ route('videos.table') }}" method="get" class="row g-2 align-items-end mb-4">
        <div class="col-12 col-md-5">
            <label for="search" class="form-label">Buscar</label>
            <input type="text" name="search" id="search" value="{{ $search }}" class="form-control"
                placeholder="Título o descripción">
        </div>
        <div class="col-12 col-md-4">
            <label for="module" class="form-label">Módulo</label>
            <select name="module" id="module" class="form-select">
                <option value="">Todos</option>
                @foreach($modules as $module)
                <option value="{{ $module->id }}" {{ $moduleId === $module->id ? 'selected' : '' }}>{{ $module->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-dark">Filtrar</button>
            @if($search !== '' || $moduleId)
            <a href="{{ route('videos.table') }}" class="btn btn-outline-secondary">Limpiar</a>
            @endif
        </div>
    </form>

    <p class="text-body-secondary">
        {{ $videos->total() }} {{ $videos->total() === 1 ? 'clase' : 'clases' }}
    </p>

    <div class="row g-4">
        @forelse($videos as $video)
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
                <img class="card-img-top" src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}">
                <div class="card-body">
                    <h5 class="card-title">{{ $video->title }}</h5>
                    <small class="text-body-secondary">{{ $video->module?->name }}</small>
                </div>
                <div class="card-footer">
                    <a href="{{ route('admin-details', $video) }}">Ver más</a>
                </div>
            </div>
        </div>
        @empty
        <p>No se encontraron clases.</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $videos->links() }}
    </div>
</section>

@endsection
