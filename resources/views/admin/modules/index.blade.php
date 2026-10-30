@extends('layouts.app')
@section('content')

<section>
    @if(session('success'))
    <div class="alert alert-success">
        <small>{{session('success')}}</small>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger">
        <small>{{session('error')}}</small>
    </div>
    @endif

    <div>
        <h1>Módulos</h1>
    </div>
    <div>
        <a href="{{ route('modulos.create') }}">Crear nuevo módulo</a>
    </div>
    <div>
        @if($modules->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Videos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($modules as $module)
                        <tr>
                            <td>{{ $module->id }}</td>
                            <td>{{ $module->name }}</td>
                            <td>{{ $module->description }}</td>
                            <td>{{ $module->videos_count }}</td>
                            <td>
                                <ul>
                                    <li>
                                        <a href="{{ route('modulos.edit', $module) }}">Editar</a>
                                    </li>
                                    <li>
                                        <form action="{{ route('modulos.destroy', $module) }}" method="post">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                onclick="return confirm('¿Estás segura de querer eliminar el módulo {{ $module->name }}? Esto también eliminará sus {{ $module->videos_count }} video(s).')">Eliminar</button>
                                        </form>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p><em>No hay módulos aún</em></p>
        @endif
    </div>
</section>

@endsection
