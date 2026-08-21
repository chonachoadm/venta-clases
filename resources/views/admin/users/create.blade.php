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
        <div>
            <a href="{{ route('usuarios.index') }}">Volver</a>
        </div>
        <h1>Creación de nuevo usuario</h1>
    </div>
    <div>
        <form action="{{ route('usuarios.store') }}" method="post">
            @csrf
            <div>
                <label for="name" class="form-label">Nombre</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" />
                @error('name')
                <span class="error">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" />
                @error('email')
                <span class="error">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label for="phone" class="form-label">Teléfono</label>
                <input type="tel" name="phone" id="phone" class="form-control" value="{{ old('phone') }}" />
                @error('phone')
                <span class="error">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" name="password" id="password" class="form-control" />
                @error('password')
                <span class="error">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" />
            </div>
            <div>
                <label for="role" class="form-label">Rol (permisos de acceso)</label>
                <select name="role" id="role">
                    <option value="2" selected>Usuario común</option>
                    <option value="1">Administradorx</option>
                </select>
            </div>
            <div>
                <button type="submit">Enviar</button>
            </div>
        </form>
    </div>
</section>

@endsection