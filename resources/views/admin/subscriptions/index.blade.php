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
                                Tipo
                            </th>
                            <th>
                                Estado de la suscripción
                            </th>
                            <th>
                                Cancelar
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
                                    @if($subscription->user)
                                        {{ $subscription->user?->name ?? $subscription->user?->email }}
                                    @else
                                        {{ $subscription->guest?->name ?? $subscription->guest?->email }}
                                    @endif
                                </td>
                                <td>
                                    {{ $subscription->package->name }}
                                </td>
                                <td>
                                    {{ $subscription instanceof \App\Models\Subscription ? 'Suscripción' : 'Pago único' }}
                                </td>
                                <td>
                                    {{ $subscription->status }}
                                </td>
                                <td>
                                    @if($subscription->status === 'active')
                                        <form
                                            action="{{ $subscription instanceof \App\Models\Subscription ? route('subscriptions.cancel', $subscription) : route('purchases.cancel', $subscription) }}"
                                            method="post">
                                            @csrf
                                            <button type="submit"
                                                onclick="return confirm('¿Estás segura de querer cancelar esta suscripción?')">Cancelar</button>
                                        </form>
                                    @else
                                        <p><em>No hay acciones</em></p>
                                    @endif
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