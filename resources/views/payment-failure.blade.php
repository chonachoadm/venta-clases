<div>
    <h1>Payment Failed</h1>
    <p>Unfortunately, your payment could not be processed.</p>
    @if ($paymentId)
        <p>Payment ID: {{ $paymentId }}</p>
    @endif
    <p>Please try again or contact support if you need help.</p>
    <a href="{{ route('checkout') }}">Try Again</a>
</div>
