<?php

namespace App\Services;

use App\Mail\FirstAccess;
use App\Models\Guest;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AccessGrantService
{
    /**
     * Turn a guest into a full User account, emailing a generated password only when the account is new.
     */
    public function grantAccess(Guest $guest, Package $package): User
    {
        $password = Str::random(10);

        $user = User::firstOrCreate(
            ['email' => $guest->email],
            [
                'name' => $guest->name,
                'phone' => $guest->phone,
                'password' => Hash::make($password),
                'role' => 2,
            ]
        );
        \Log::info('Usuario creado');
        
        if ($user->wasRecentlyCreated) {
            Mail::to($guest->email)->send(new FirstAccess($user, $password));
        }
            
        \Log::info('Mail de bienvenida enviado');
        $guest->delete();
        
        \Log::info('Invitado eliminado');
        return $user;
    }

    /**
     * Activate a subscription (idempotent — safe to call more than once for the same event).
     */
    public function activateSubscription(Subscription $subscription, string $externalId): void
    {
        if ($subscription->status === 'active') {
            return;
        }

        $user = $this->grantAccess($subscription->guest, $subscription->package);

        $subscription->update([
            'status' => 'active',
            'external_id' => $externalId,
            'expires_at' => now()->addMonths($subscription->package->month_amount),
            'guest_id' => null,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Activate a one-time purchase (idempotent — safe to call more than once for the same event).
     */
    public function activatePurchase(Purchase $purchase, string $externalId): void
    {
        if ($purchase->status === 'active') {
            return;
        }

        \Log::info('Usuario creandose');
        $user = $this->grantAccess($purchase->guest, $purchase->package);

        \Log::info('Compra actualizandose');
        $purchase->update([
            'status' => 'active',
            'external_id' => $externalId,
            'expires_at' => now()->addMonths($purchase->package->month_amount),
            'guest_id' => null,
            'user_id' => $user->id,
        ]);
    }
}
