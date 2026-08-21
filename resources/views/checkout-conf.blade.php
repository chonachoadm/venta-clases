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
        Estás comprando el paquete {{ $purchase->package->name }}
    </li>
    <li>
        Precio: $ {{ $purchase->package->total_price }}
    </li>
</ul>

<a href="{{ route('payment.start', [
    'purchase' => $purchase->id,
    'provider' => 'mercadopago'
]) }}">
    Pagar con MercadoPago
</a>

<a href="{{ route('payment.start', [
    'purchase' => $purchase->id,
    'provider' => 'paypal'
]) }}">
    Pagar con PayPal
</a>