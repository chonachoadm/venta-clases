<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Services\PayPalPaymentService;
use Illuminate\Console\Command;

class ProvisionPayPalPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paypal:provision-plans {--force : Re-provision packages that already have a paypal_plan_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a PayPal product and billing plan for each package and cache the plan id';

    /**
     * Execute the console command.
     */
    public function handle(PayPalPaymentService $paymentService): int
    {
        $packages = Package::query()
            ->when(! $this->option('force'), fn ($query) => $query->whereNull('paypal_plan_id'))
            ->get();

        if ($packages->isEmpty()) {
            $this->info('No packages to provision.');

            return self::SUCCESS;
        }

        foreach ($packages as $package) {
            try {
                $planId = $paymentService->provisionPlan($package);
                $this->info("Package #{$package->id} ({$package->name}): plan {$planId}");
            } catch (\Throwable $e) {
                $this->error("Package #{$package->id} ({$package->name}): {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
