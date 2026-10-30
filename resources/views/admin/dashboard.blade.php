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
        @if($errors->any())
            <div class="alert alert-danger">
                <small>{{ $errors->first() }}</small>
            </div>
        @endif

        <div>
            <h1>Panel de administración</h1>
            <!-- <form action="{{ route('logout') }}" method="POST">
                                            @csrf
                                            <button type="submit">Cerrar sesión</button>
                                        </form> -->
        </div>

        <div>
            <h2>Cotización del dólar</h2>
            <form action="{{ route('exchange-rate.update') }}" method="post">
                @csrf
                <label for="exchange_rate">Pesos por dólar</label>
                <input type="number" step="0.01" min="1" id="exchange_rate" name="exchange_rate"
                    value="{{ old('exchange_rate', $exchangeRate) }}" required>
                @error('exchange_rate')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
                <button type="submit">Guardar</button>
            </form>
        </div>

        <div class="row my-4 border px-4 py-2 bg-primary-subtle">
            <div class="col-12">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-10 my-2">
                        <h2 class="mb-0 fw-bold">Módulos</h2>
                        <a href="{{ route('modulos.index') }}">Gestionar módulos</a>
                    </div>
                </div>

                <div class="row justify-content-around">
                    <div class="col-12 col-md-5 my-4">
                        <h3>Importar playlist</h3>
                        <form action="{{ route('videos.import-playlist') }}" method="post"
                            class="row justify-content-center">
                            @csrf

                            <div class="mb-2 row justify-content-between">
                                <label for="playlist" class="p-0 form-label">URL o ID de la playlist</label>
                                <input type="text" id="playlist" name="playlist" class="form-control"
                                    value="{{ old('playlist') }}" required>
                            </div>

                            <div class="mb-2 row justify-content-between align-items-center">
                                <div class="col-5">
                                    <div class="row justify-content-between">
                                        <label for="playlist_module_id" class="p-0 form-label">Módulo existente</label>
                                        <select id="playlist_module_id" name="module_id" class="form-select">
                                            <option value="">-- Ninguno --</option>
                                            @foreach($modules as $module)
                                                <option value="{{ $module->id }}">{{ $module->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="row justify-content-between">
                                        <label for="playlist_new_module_name" class="p-0 form-label">Módulo nuevo</label>
                                        <input type="text" id="playlist_new_module_name" name="new_module_name"
                                            class="form-control" value="{{ old('new_module_name') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="my-2 row justify-content-end">
                                <div class="col-5 row">
                                    <button type="submit" class="btn btn-primary">Importar playlist</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-12 col-md-5 my-4">
                        <h3>Importar video individual</h3>
                        <form action="{{ route('videos.import-video') }}" method="post" class="row justify-content-center">
                            @csrf

                            <div class="mb-2 row justify-content-between">
                                <label for="video" class="p-0 form-label">URL o ID del video</label>
                                <input type="text" id="video" name="video" class="form-control" value="{{ old('video') }}"
                                    required>
                            </div>

                            <div class="mb-2 row justify-content-between align-items-center">
                                <div class="col-5">
                                    <div class="row justify-content-between">
                                        <label for="video_module_id" class="p-0 form-label">Módulo existente</label>
                                        <select id="video_module_id" name="module_id" class="form-select">
                                            <option value="">-- Ninguno --</option>
                                            @foreach($modules as $module)
                                                <option value="{{ $module->id }}">{{ $module->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="row justify-content-between">
                                        <label for="video_new_module_name" class="p-0 form-label">Módulo nuevo</label>
                                        <input type="text" id="video_new_module_name" name="new_module_name"
                                            class="form-control" value="{{ old('new_module_name') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="my-2 row justify-content-end">
                                <div class="col-5 row">
                                    <button type="submit" class="btn btn-primary">Importar video</button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row my-4 px-4 py-2" id="clases">
            <div class="col-12">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-10 my-2">
                        <div class="d-flex align-items-center gap-4">
                            <h2 class="mb-0 fw-bold">Clases</h2>
                            <form action="{{ route('dashboard') . '#clases' }}" method="get" class="d-flex align-items-center">
                                <div>
                                    <input type="text" name="search" id="search" aria-label="Buscador de clase"
                                    placeholder="Buscá por título" class="border border-end-0 rounded-start-2 py-1 px-2">
                                </div>
                                <div>
                                    <button type="submit"
                                    class="border border-start-0 rounded-end-2 py-1 px-2 bg-dark-subtle">Buscar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @foreach ([['id' => 'carouselWide', 'size' => 3, 'visibility' => 'd-none d-md-block', 'col' => 'col-4'], ['id' => 'carouselNarrow', 'size' => 1, 'visibility' => 'd-md-none', 'col' => 'col-12']] as $carousel)
                    <div id="{{ $carousel['id'] }}" class="carousel slide pb-4 {{ $carousel['visibility'] }}"
                        data-bs-theme="dark">
                        <div class="carousel-indicators">
                            @foreach ($videos->chunk($carousel['size']) as $chunk)
                                <button type="button" data-bs-target="#{{ $carousel['id'] }}" data-bs-slide-to="{{ $loop->index }}"
                                    class="{{ $loop->first ? 'active' : '' }}" @if ($loop->first) aria-current="true" @endif
                                    aria-label="Slide {{ $loop->iteration }}"></button>
                            @endforeach
                        </div>
                        <div class="carousel-inner p-4">
                            @foreach ($videos->chunk($carousel['size']) as $chunk)
                                <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                                    <div class="row g-3 px-5">
                                        @foreach ($chunk as $video)
                                            <div class="{{ $carousel['col'] }}">
                                                <img class="img-fluid" src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}">
                                                <div>
                                                    <h4>{{ $video->title }}</h4>
                                                </div>
                                                <div>
                                                    <a href="{{ route('admin-details', $video) }}">Ver más</a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#{{ $carousel['id'] }}"
                            data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#{{ $carousel['id'] }}"
                            data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                @endforeach
                <!-- <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Nombre</th>
                                            <th scope="col">Thumbnail</th>
                                            <th scope="col">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($videos as $video)
                                            <tr>
                                                <td>{{ $video->id }}</td>
                                                <td>{{ $video->title }}</td>
                                                <td><img class="img-fluid" src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}">
                                                </td>
                                                <td><a href="{{ route('admin-details', $video) }}">Ver</a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div> -->
            </div>
        </div>
        <div>
            <h2>
                Usuarios
            </h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">
                                ID
                            </th>
                            <th scope="col">
                                Nombre
                            </th>
                            <th scope="col">
                                Email
                            </th>
                            <th scope="col">
                                Completitud
                            </th>
                            <th scope="col">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    {{ $user->id }}
                                </td>
                                <td>
                                    {{ $user->name }}
                                </td>
                                <td>
                                    {{ $user->email }}
                                </td>
                                <td>
                                    @if($user->isAdmin())
                                        <em>Admin</em>
                                    @else
                                        {{ round($user->watchedVideos()->whereNotNull('watched_at')->count() / $totalVideos * 100)}}%
                                    @endif
                                </td>
                                <td>
                                    <ul>
                                        <li>
                                            <a href="{{ route('usuarios.show', $user->id) }}">Ver</a>
                                        </li>
                                        <li>
                                            <a href="#">Editar</a>
                                        </li>
                                        <li>
                                            <form action="#" method="post">
                                                @csrf
                                                <button type="submit">Eliminar</button>
                                            </form>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </section>

@endsection