<div>
    <h1>Payment Pending</h1>
    <p>Your payment is being processed. This may take a few moments.</p>
    @if ($paymentId)
        <p>Payment ID: {{ $paymentId }}</p>
    @endif
    <p>We will notify you once the payment is confirmed.</p>
    <a href="{{ route('index') }}">Back to Videos</a>
</div>
