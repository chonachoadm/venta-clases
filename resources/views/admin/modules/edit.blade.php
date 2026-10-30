@extends('layouts.app')
@section('content')

<section>
    <div>
        <div>
            <a href="{{ route('modulos.index') }}">Volver</a>
        </div>
        <h1>Editar módulo</h1>
    </div>
    <div>
        <form action="{{ route('modulos.update', $module) }}" method="post">
            @csrf
            @method('PUT')
            <div>
                <label for="name" class="form-label">Nombre</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $module->name) }}" />
                @error('name')
                <span class="error">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label for="description" class="form-label">Descripción</label>
                <textarea name="description" id="description" class="form-control">{{ old('description', $module->description) }}</textarea>
                @error('description')
                <span class="error">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <button type="submit">Guardar</button>
            </div>
        </form>
    </div>
</section>

@endsection
