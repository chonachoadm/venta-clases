<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Package::create([
            'name' => '1 mes',
            'total_price' => 24000.00,
            'price_per_month' => 24000.00,
            'month_amount' => 1,
            'mercadopago_plan_id' => '5b70323eddd843118147ce070c17ab2f',
        ]);
        Package::create([
            'name' => '3 meses',
            'total_price' => 60000.00,
            'price_per_month' => 20000.00,
            'month_amount' => 3,
            'mercadopago_plan_id' => '0bb94ab2ed4246d1919ae93e36447b92',
        ]);
        Package::create([
            'name' => '6 meses',
            'total_price' => 108000.00,
            'price_per_month' => 18000.00,
            'month_amount' => 6,
            'mercadopago_plan_id' => '26244d95d90d494083cf36210f880ade',
        ]);
        Package::create([
            'name' => '1 año',
            'total_price' => 192000.00,
            'price_per_month' => 16000.00,
            'month_amount' => 12,
            'mercadopago_plan_id' => '051a0f452c31432fbd8f41ec9626aefc',
        ]);
    }
}
