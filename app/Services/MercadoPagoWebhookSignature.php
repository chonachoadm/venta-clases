<?php

namespace App\Services;

use Illuminate\Http\Request;

class MercadoPagoWebhookSignature
{
    /**
     * Verify the x-signature header of a Mercado Pago webhook (HMAC-SHA256 over "id;request-id;ts").
     */
    public function isValid(Request $request): bool
    {
        $secret = config('services.mercadopago.webhook_secret');

        if (! $secret) {
            \Log::warning('Mercado Pago webhook rejected: MERCADOPAGO_WEBHOOK_SECRET not configured');

            return false;
        }

        $parts = [];

        foreach (explode(',', (string) $request->header('x-signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            $parts[$key] = $value;
        }

        $timestamp = $parts['ts'] ?? null;
        $signature = $parts['v1'] ?? null;

        if (! $timestamp || ! $signature) {
            \Log::warning('Mercado Pago webhook rejected: x-signature header missing ts or v1', [
                'x-signature' => $request->header('x-signature'),
            ]);

            return false;
        }

        // PHP turns the "data.id" query parameter into "data_id".
        $dataId = $request->query('data_id') ?? $request->input('data.id');

        $manifest = '';

        if ($dataId !== null) {
            $manifest .= 'id:'.strtolower((string) $dataId).';';
        }

        if ($request->header('x-request-id')) {
            $manifest .= 'request-id:'.$request->header('x-request-id').';';
        }

        $manifest .= "ts:{$timestamp};";

        $computed = hash_hmac('sha256', $manifest, $secret);
        $valid = hash_equals($computed, $signature);

        if (! $valid) {
            // TEMPORARY diagnostics while debugging a signature mismatch (2026-11-02). Remove once resolved; logs no secret.
            \Log::warning('Mercado Pago webhook signature mismatch — diagnostics', [
                'x_signature_header' => $request->header('x-signature'),
                'x_request_id_header' => $request->header('x-request-id'),
                'query_string' => $request->getQueryString(),
                'data_id_used' => $dataId,
                'manifest_built' => $manifest,
                'signature_received' => $signature,
                'signature_computed' => $computed,
            ]);
        }

        return $valid;
    }
}
