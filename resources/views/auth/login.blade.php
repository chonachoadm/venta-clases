@extends('layouts.app')
@section('content')

<section>
    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('message'))
    <div class="alert alert-warning">{{ session('message') }}</div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    <form action="{{ route('login') }}" method="post">
        @csrf
        <div>
            <label for="email">Correo electrónico</label>
            <input type="email" name="email" id="email" />
            @error('email')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" />
            @error('password')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
        <div>
            <button type="submit">Ingresar</button>
        </div>
    </form>
</section>

@endsection