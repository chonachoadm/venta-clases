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
        Precio: $ {{ $package->total_price }} USD / $ {{ $package->totalPriceArs() }} ARS
    </li>
</ul>

<div>
    <label>
        <input type="radio" name="payment_type" value="subscription" checked onchange="toggleCheckoutPaymentType()">
        Suscripción mensual (se renueva automáticamente)
    </label>
    <label>
        <input type="radio" name="payment_type" value="one_time" onchange="toggleCheckoutPaymentType()">
        Pago único
    </label>
</div>

<div id="subscription-options">
    <form action="{{ route('checkout.mercadopago.subscription') }}" method="post">
        @csrf
        <button type="submit">Pagar con MercadoPago</button>
    </form>

    <form action="{{ route('checkout.paypal.subscription') }}" method="post">
        @csrf
        <button type="submit">Pagar con PayPal</button>
    </form>
</div>

<div id="one-time-options" style="display:none">
    <form action="{{ route('checkout.mercadopago.onetime') }}" method="post">
        @csrf
        <button type="submit">Pagar con MercadoPago</button>
    </form>

    <form action="{{ route('checkout.paypal.onetime') }}" method="post">
        @csrf
        <button type="submit">Pagar con PayPal</button>
    </form>
</div>

<script>
    function toggleCheckoutPaymentType() {
        const isOneTime = document.querySelector('input[name="payment_type"]:checked').value === 'one_time';
        document.getElementById('subscription-options').style.display = isOneTime ? 'none' : 'block';
        document.getElementById('one-time-options').style.display = isOneTime ? 'block' : 'none';
    }
</script>