@extends('layouts.app')
@section('content')

<section>
    <h1>Elegí tu contraseña</h1>

    <form action="{{ route('password.update') }}" method="post">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email">Correo electrónico</label>
            <input type="email" name="email" id="email" value="{{ old('email', $email) }}" required />
            @error('email')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
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
    <div>
        <a href="{{ route('password.request') }}">Pedir un enlace nuevo</a>
    </div>
</section>

@endsection
