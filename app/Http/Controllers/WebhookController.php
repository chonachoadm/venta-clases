<?php

namespace App\Http\Controllers;

use App\Mail\FirstAccess;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MercadoPagoPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WebhookController extends Controller
{

    public function __construct(private MercadoPagoPaymentService $mercadoPagoService)
    {}
    /**
     * Handle Mercado Pago webhook notifications
     */
    public function handleMercadoPagoWebhook(Request $request)
    {
        $type = $request->input('type');
        $data = $request->input('data');

        if ($type === 'subscription_preapproval') {
            return $this->handleSubscription($data);
        }

        return response()->json(['ignored' => true]);
    }


    public function handleSubscription(array $data)
    {
        $subscriptionId = $data['id'] ?? null;

        if (!$subscriptionId) {
            return response()->json(['error' => 'missing id']);
        }

        $client = new \MercadoPago\Client\PreApproval\PreApprovalClient();
        try {
            $preapproval = $client->get($subscriptionId);

            \Log::info('Preapproval obtenido', [
                'id' => $preapproval->id,
                'status' => $preapproval->status,
                'external_reference' => $preapproval->external_reference,
            ]);
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            $response = $e->getApiResponse();
            $content = $response ? $response->getContent() : [];

            \Log::error('Error consultando preapproval', [
                'subscription_id' => $subscriptionId,
                'message' => $e->getMessage(),
                'content' => $content,
                'status' => $response?->getStatusCode(),
            ]);

            return response()->json(['ok' => true]);
        }

        $subscription = Subscription::where('id', $preapproval->external_reference)->first();

        if (!$subscription) {
            return response()->json(['error' => 'not found']);
        }

        $guest = $subscription->guest;

        $password = Str::random(10);

        if ($preapproval->status === 'authorized') {

            $user = User::firstOrCreate([
                'name' => $guest->name,
                'email' => $guest->email,
                'phone' => $guest->phone,
                'password' => Hash::make($password),
                'role' => 2,
            ]);

            $subscription->update([
                'status' => 'active',
                'external_id' => $preapproval->id,
                'expires_at' => now()->addMonths($subscription->package->month_amount),
                'guest_id' => null,
                'user_id' => $user->id,
            ]);

            $guest->delete();

            Mail::to('chonachoadm@gmail.com')->send(new FirstAccess($user, $password));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle PayPal webhook notifications
     */
    public function handlePayPalWebhook(Request $request)
    {
        \Log::info('PayPal webhook received', $request->all());

        return response()->json([
            'status' => 'ok',
        ]);
    }
}
