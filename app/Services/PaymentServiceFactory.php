<?php

namespace App\Services;

class PaymentServiceFactory
{
    /**
     * Create a payment service instance for the given provider.
     *
     * @param string $provider
     * @return PaymentService
     * @throws \InvalidArgumentException
     */
    public static function make(string $provider): PaymentService
    {
        return match ($provider) {
            'mercadopago' => new MercadoPagoPaymentService(),
            'paypal' => new PayPalPaymentService(),
            default => throw new \InvalidArgumentException("Unsupported payment provider: {$provider}"),
        };
    }
}
