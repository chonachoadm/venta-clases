<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Package;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\PreApprovalPlan\PreApprovalPlanClient;

class CreateMercadoPagoPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-mercado-pago-plans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
    // $token = config('services.mercadopago.access_token');

    // if (!$token) {
    //     $this->error('Mercado Pago access token not configured.');
    //     return self::FAILURE;
    // }

    // MercadoPagoConfig::setAccessToken($token);

    // $client = new PreApprovalPlanClient();

    // $packages = Package::all();

    // foreach ($packages as $package) {

    //     try {

    //         $response = $client->create([
    //             "reason" => $package->name . " - Acceso a clases",
    //             "auto_recurring" => [
    //                 "frequency" => $package->month_amount,
    //                 "frequency_type" => "months",
    //                 "transaction_amount" => (float) $package->total_price,
    //                 "currency_id" => "ARS",
    //             ],
    //             "back_url" => "https://www.google.com",
    //         ]);

    //         $this->info("{$package->name}: {$response->id}");

    //     } catch (\MercadoPago\Exceptions\MPApiException $e) {

    //         $apiResponse = $e->getApiResponse();

    //         $this->error("Error creando {$package->name}");

    //         dump($apiResponse->getContent());

    //         return self::FAILURE;
    //     }
    // }

    // return self::SUCCESS;
    }
}
