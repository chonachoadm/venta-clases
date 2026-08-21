<div>
    <h1>Pago aprobado!</h1>
    <p>Gracias por tu compra.</p>
    <p>Te llegará un correo con un link para configurar tu contraseña.</p>
    <p>Una vez la contraseña esté hecha, tendrás acceso a todos los videos hasta {{ $purchase->expires_at->format('d/m/Y') }}.</p>

    @unless(\App\Models\User::where('email', $email)->exists())
    <button onClick="togglePasswordCreationDisplay()">Crear contraseña ahora</button>
    @endunless

    <a href="{{ route('index') }}">Volver a Clases</a>
    <div id="password-creation" style="display:none">
        <form action="{{ route('guest.register') }}" method="post">
            @csrf
            <div>
                <label for="password">Contraseña</label>
                <input type="password" name="password" id="password">
            </div>
            <div>
                <label for="password_confirmation">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" id="password_confirmation">
            </div>
            <div>
                <input type="hidden" name="name" value="{{ $name }}">
                <input type="hidden" name="email" value="{{ $email }}">
                <input type="hidden" name="phone" value="{{ $phone }}">
            </div>
            <div>
                <button type="submit">Guardar</button>
            </div>
        </form>
    </div>
</div>

@if(isset($email))
<script>
    localStorage.setItem('email', @json($email))
    localStorage.setItem('name', @json($name))
    localStorage.setItem('phone', @json($phone))

    function togglePasswordCreationDisplay() {
        const passwordCreationForm = document.getElementById('password-creation');

        passwordCreationForm.style.display = 'block';
    }
</script>
@endif