@extends('layouts.app')
@section('content')

<section>
    @if(session('message'))
    <div class="alert alert-warning">{{ session('message') }}</div>
    @endif
    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <h1>Elegí tu contraseña</h1>
    <p>Antes de continuar tenés que elegir una contraseña nueva.</p>

    <form action="{{ route('password.change.update') }}" method="post">
        @csrf
        <div>
            <label for="password">Nueva contraseña</label>
            <input type="password" name="password" id="password" minlength="6" required />
            <small>Mínimo 6 caracteres.</small>
            @error('password')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <label for="password_confirmation">Repetí la contraseña</label>
            <input type="password" name="password_confirmation" id="password_confirmation" minlength="6" required />
        </div>
        <div>
            <button type="submit">Guardar contraseña</button>
        </div>
    </form>

    <form action="{{ route('logout') }}" method="post">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>
</section>

@endsection
