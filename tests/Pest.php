<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Headers Mercado Pago would send for a webhook whose data.id is $dataId, signed with $secret.
 *
 * @return array{x-signature: string, x-request-id: string}
 */
function mercadoPagoSignedHeaders(string $dataId, string $secret, string $requestId = 'req-1', string $timestamp = '1704908010'): array
{
    $manifest = ($dataId === '' ? '' : 'id:'.strtolower($dataId).';')."request-id:{$requestId};ts:{$timestamp};";

    return [
        'x-signature' => "ts={$timestamp},v1=".hash_hmac('sha256', $manifest, $secret),
        'x-request-id' => $requestId,
    ];
}
