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
            <h1>Dashboard</h1>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit">Cerrar sesión</button>
            </form>
        </div>

        <div class="table-responsive">
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
                            <td scope="row">{{ $video->id }}</td>
                            <td>{{ $video->title }}</td>
                            <td><img class="img-fluid w-25" src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}"></td>
                            <td><a href="{{ route('admin-details', $video) }}">Ver</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
                                <td scope="row">
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
                                            <a href="">Editar</a>
                                        </li>
                                        <li>
                                            <form action="" method="post">
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
            </div>
        </div>
    </section>

@endsection