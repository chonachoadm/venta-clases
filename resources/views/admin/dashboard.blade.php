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

        <div class="row my-4 border p-4 bg-primary-subtle">
            <div class="col-12">
                <div class="row justify-content-around">
                    <div class="col-12 col-md-5 my-2">
                        <h2 class="mb-0 fw-bold">Módulos</h2>
                        <a href="{{ route('modulos.index') }}">Gestionar módulos</a>
                    </div>
                    <div class="d-none d-md-block col-12 col-md-5"></div>
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
                        <div class="d-flex align-items-center justify-content-between gap-4">
                            <h2 class="mb-0 fw-bold">Clases <small class="text-body-secondary fs-6">({{ $totalVideos }})</small></h2>
                            <a href="{{ route('videos.table') }}">Ver todas las clases</a>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <div class="col-12 col-md-10">
                        <div class="row g-3">
                            @forelse ($latestVideos as $video)
                                <div class="col-6 col-md-4 col-lg-2">
                                    <img class="img-fluid" src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}">
                                    <div>
                                        <small>{{ $video->title }}</small>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin-details', $video) }}">Ver más</a>
                                    </div>
                                </div>
                            @empty
                                <p>Todavía no hay clases importadas.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
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