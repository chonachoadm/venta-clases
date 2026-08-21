<div>
    <h1>Elegí tu propuesta</h1>

    <div class="packages-container">
        @foreach ($packages as $package)
            <div class="package-card">
                <h2>{{ $package->name }}</h2>

                @if((24000 * $package->month_amount) != $package->total_price)
                    <p><s>${{ 24000 * $package->month_amount }} ARS /
                            ${{ round($package->month_amount * 24000 / config('services.paypal.exchange_rate')) }} USD</s></p>
                @endif

                <p class="price">${{ $package->total_price }} ARS /
                    ${{ round($package->total_price / config('services.paypal.exchange_rate')) }} USD</p>
                <small> ${{ $package->price_per_month }} ARS /
                    ${{ round($package->price_per_month / config('services.paypal.exchange_rate')) }} USD por mes</small>
            </div>
        @endforeach
    </div>

    <form action="{{ route('checkout-confirmation') }}" method="post">
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
                <span class="error">{{ $message }}</span>
            @enderror
        </div>

        <div class="guest-info">
            <h3>Tu informacion</h3>

            <div>
                <label for="name">Nombre</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                @error('name')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required onBlur="">
                @error('email')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="phone">Número de telefono</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required>
                @error('phone')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <button type="submit">Continuar</button>
    </form>
    <div>
        <a href="{{ route('showLogin') }}">Ya tengo mi cuenta</a>
    </div>
</div>