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
        <h1>Usuarios</h1>
    </div>
    <div>
        <a href="{{ route('usuarios.create') }}">Crear nuevo usuario</a>
    </div>
    <div>
        <form action="{{ route('usuarios.index') }}" method="get" id="filterForm">
            @csrf
            <div>
                <label for="filter" class="form-label">Filtrar</label>
                <select name="filter" id="filter">
                    <option value="">Todos</option>
                    <option value="active" {{ request('filter') == 'active' ? 'selected' : '' }}>Activos</option>
                    <option value="inactive" {{ request('filter') == 'inactive' ? 'selected' : '' }}>Inactivos</option>
                </select>
            </div>
        </form>
    </div>
    <div>
        <table>
            <thead>
                <tr>
                    <th>
                        ID
                    </th>
                    <th>
                        Nombre
                    </th>
                    <th>
                        Email
                    </th>
                    <th>
                        Completitud
                    </th>
                    <th>
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
                        {{ $user->watchedPercentage($totalVideos) }}%
                        @endif
                    </td>
                    <td>
                        <ul>
                            <li>
                                <a href="{{ route('usuarios.show', $user) }}">Ver</a>
                            </li>
                            <li>
                                <a href="{{ route('usuarios.edit', $user) }}">Editar</a>
                            </li>
                            <li>
                                <form action="{{ route('usuarios.destroy', $user) }}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" onclick="return confirm('¿Estás seguro de querer eliminar {{ $user->name }}?')">Eliminar</button>
                                </form>
                            </li>
                        </ul>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<script>
    document.getElementById('filter').addEventListener('change', function () {
        document.getElementById('filterForm').submit();
    });
</script>
@endsection