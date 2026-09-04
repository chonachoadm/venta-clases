<div>
    <h1>Elegí tu propuesta</h1>

    <div class="packages-container">
        @foreach ($packages as $package)
            <div class="package-card">
                <h2>{{ $package->name }}</h2>

                @if((25 * $package->month_amount) != $package->total_price)
                    <p><s>${{ 25 * $package->month_amount }} USD /
                            ${{ round(25 * $package->month_amount * config('services.exchange_rate'), -3) }} ARS</s></p>
                @endif

                <p class="price">${{ $package->total_price }} USD /
                    ${{ $package->totalPriceArs() }} ARS</p>
                <small> ${{ $package->price_per_month }} USD /
                    ${{ $package->pricePerMonthArs() }} ARS por mes</small>
            </div>
        @endforeach
    </div>

    <form action="{{ route('checkout.confirmation') }}" method="post">
        @csrf

        <div class="package-selection">
            <label>Select Package:</label>
            @foreach ($packages as $package)
                <div>
                    <input type="radio" id="package_{{ $package->id }}" name="package_id" value="{{ $package->id }}"
                        required>
                    <label for="package_{{ $package->id }}">
                        {{ $package->name }}
                    </label>
                </div>
            @endforeach
            @error('package_id')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

        <div class="guest-info">
            <h3>Tu informacion</h3>

            <div>
                <label for="name">Nombre</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                @error('name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                <small>El correo debe estar registrado en una cuenta de MercadoPago o de PayPal</small>
                @error('email')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="phone">Número de telefono</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required>
                @error('phone')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <button type="submit">Continuar</button>
    </form>
    <div>
        <a href="{{ route('showLogin') }}">Ya tengo mi cuenta</a>
    </div>
</div>