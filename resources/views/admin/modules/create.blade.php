@extends('layouts.app')
@section('content')

<section>
    <div>
        <div>
            <a href="{{ route('modulos.index') }}">Volver</a>
        </div>
        <h1>Creación de nuevo módulo</h1>
    </div>
    <div>
        <form action="{{ route('modulos.store') }}" method="post">
            @csrf
            <div>
                <label for="name" class="form-label">Nombre</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" />
                @error('name')
                <span class="error">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label for="description" class="form-label">Descripción</label>
                <textarea name="description" id="description" class="form-control">{{ old('description') }}</textarea>
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
