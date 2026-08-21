@extends('layouts.app')
@section('content')

<section>
    @if(session('message'))
    <div class="alert alert-warning">{{ session('message') }}</div>
    @endif
    <form action="{{ route('login') }}" method="post">
        @csrf
        <div>
            <label for="email">Correo electrónico</label>
            <input type="email" name="email" id="email" />
        </div>
        <div>
            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" />
        </div>
        <div>
            <button type="submit">Ingresar</button>
        </div>
    </form>
</section>

@endsection