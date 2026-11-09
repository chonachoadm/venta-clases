<div style="font-family: Arial, Helvetica, sans-serif; max-width: 480px; margin: 0 auto; color: #212529;">
    <h1 style="font-size: 22px;">¡Gracias por tu compra, {{ $user->name }}!</h1>

    <p>Ya creamos tu cuenta con este correo:</p>
    <p><strong>{{ $user->email }}</strong></p>

    <p>Para empezar, elegí tu contraseña:</p>

    <p style="margin: 24px 0;">
        <a href="{{ $setPasswordUrl }}"
            style="background-color: #212529; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: inline-block;">
            Crear mi contraseña
        </a>
    </p>

    <p><small>El enlace vence en 24 horas y solo se puede usar una vez. Si venció, pedí uno nuevo desde "¿Olvidaste tu contraseña?" en la pantalla de ingreso.</small></p>

    <p><small>Si el botón no funciona, copiá y pegá esta dirección en tu navegador:<br>{{ $setPasswordUrl }}</small></p>
</div>
