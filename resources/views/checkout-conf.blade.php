<div>
    <h2>Confirmá que los datos sean correctos</h2>
</div>

<ul>
    <li>
        Tu nombre: {{ $guest->name }}
    </li>
    <li>
        Tu correo electronico: {{ $guest->email }}
    </li>
    <li>
        Tu número de telefono: {{ $guest->phone }}
    </li>
    <li>
        Estás comprando el paquete {{ $package->name }}
    </li>
    <li>
        Precio: $ {{ $package->total_price }}
    </li>
</ul>


<form action="{{ route('checkout.mercadopago') }}" method="post">
    @csrf
    <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
    <input type="hidden" name="guest_id" value="{{ $guest->id }}">
    <input type="hidden" name="package_id" value="{{ $package->id }}">
    
    <button type="submit">Pagar con MercadoPago</button>
</form>

<form action="{{ route('checkout.paypal') }}" method="post">
    @csrf
    <button type="submit">Pagar con PayPal</button>
</form>