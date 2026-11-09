@extends('layouts.app')
@section('content')

<section>
    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <h1>¿Olvidaste tu contraseña?</h1>
    <p>Ingresá tu correo y te enviamos un enlace para elegir una contraseña nueva. El enlace vence en 24 horas.</p>

    <form action="{{ route('password.email') }}" method="post">
        @csrf
        <div>
            <label for="email">Correo electrónico</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required />
            @error('email')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <button type="submit">Enviar enlace</button>
        </div>
    </form>
    <div>
        <a href="{{ route('showLogin') }}">Volver a ingresar</a>
    </div>
</section>

@endsection
