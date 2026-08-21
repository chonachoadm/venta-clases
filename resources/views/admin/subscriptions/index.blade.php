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
    @if(session('error'))
    <div class="alert alert-danger">
        <small>{{session('error')}}</small>
    </div>
    @endif

    <div>
        <h1>Suscripciones</h1>
    </div>
    <div>
        <a href="{{ route('suscripciones.create') }}">Crear nueva suscripción</a>
    </div>
    <div>
        <form action="{{ route('suscripciones.index') }}" method="get" id="filterForm">
            @csrf
            <div>
                <label for="filter" class="form-label">Filtrar</label>
                <select name="filter" id="filter">
                    <option value="">Todas</option>
                    <option value="1" {{ request('filter') == 1 ? 'selected' : '' }}>1 mes</option>
                    <option value="3" {{ request('filter') == 3 ? 'selected' : '' }}>3 meses</option>
                    <option value="6" {{ request('filter') == 6 ? 'selected' : '' }}>6 meses</option>
                    <option value="12" {{ request('filter') == 12 ? 'selected' : '' }}>12 meses</option>
                </select>
            </div>
        </form>
    </div>
    <div>
        @if($subscriptions->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>
                        ID
                    </th>
                    <th>
                        Nombre de usuario
                    </th>
                    <th>
                        Paquete adquirido
                    </th>
                    <th>
                        Estado de la suscripción
                    </th>
                    <th>
                        Acciones
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach($subscriptions as $subscription)
                <tr>
                    <td>
                        {{ $subscription->id }}
                    </td>
                    <td>
                        {{ $subscription->user->name ?? $subscription->user->email }}
                    </td>
                    <td>
                        {{ $subscription->package->name }}
                    </td>
                    <td>
                        {{ $subscription->status }}
                    </td>
                    <td>
                        <ul>
                            <li>
                                <a href="{{ route('suscripciones.show', $subscription) }}">Ver</a>
                            </li>
                            <li>
                                <a href="{{ route('suscripciones.edit', $subscription) }}">Editar</a>
                            </li>
                            @if($subscription->status === 'active')
                            <li>
                                <form action="{{ route('subscriptions.cancel', $subscription) }}" method="post">
                                    @csrf
                                    <button type="submit" onclick="return confirm('¿Estás segura de querer cancelar esta suscripción?')">Cancelar</button>
                                </form>
                            </li>
                            @endif
                        </ul>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p><em>No hay suscripciones aún</em></p>
        @endif
    </div>
</section>

<script>
    document.getElementById('filter').addEventListener('change', function () {
        document.getElementById('filterForm').submit();
    });
</script>
@endsection