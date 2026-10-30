<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminModuleController;
use App\Http\Controllers\AdminSubscriptionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminVideoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlanSelectionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use MercadoPago\Client\PreApproval\PreApprovalClient;

Route::get('/', [VideoController::class, 'indexGuest'])->name('index');
Route::get('/plan', [PlanSelectionController::class, 'index'])->name('plan-selection');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout/confirmation', [CheckoutController::class, 'showCheckout'])->name('checkout.confirmation');
Route::post('/checkout/mercadopago/suscripcion', [CheckoutController::class, 'payWithMercadoPagoSubscription'])->name('checkout.mercadopago.subscription');
Route::post('/checkout/mercadopago/pago-unico', [CheckoutController::class, 'payWithMercadoPagoOneTime'])->name('checkout.mercadopago.onetime');
Route::post('/checkout/paypal/suscripcion', [CheckoutController::class, 'payWithPayPalSubscription'])->name('checkout.paypal.subscription');
Route::post('/checkout/paypal/pago-unico', [CheckoutController::class, 'payWithPayPalOneTime'])->name('checkout.paypal.onetime');
Route::get('/subscription/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/subscription/failure', [PaymentController::class, 'failure'])->name('payment.failure');
Route::get('/subscription/pending', [PaymentController::class, 'pending'])->name('payment.pending');

Route::post('/webhooks/mercadopago', [WebhookController::class, 'handleMercadoPagoWebhook'])->name('webhooks.mercadopago');
Route::post('/payment/paypal-webhook', [WebhookController::class, 'handlePayPalWebhook'])->name('payment.paypal-webhook');

Route::get('/login', [AuthController::class, 'showLogin'])->name('showLogin');
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::get('/test-preapproval/{id}', function ($id) {

    $client = new PreApprovalClient;

    $preapproval = $client->get($id);

    dd([
        'id' => $preapproval->id,
        'status' => $preapproval->status,
        'external_reference' => $preapproval->external_reference,
        'payer_email' => $preapproval->payer_email,
    ]);
});

Route::middleware('admin')->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('dashboard');
    Route::post('/admin/exchange-rate', [AdminController::class, 'updateExchangeRate'])->name('exchange-rate.update');
    Route::get('/admin/videos', [AdminController::class, 'videosTable'])->name('videos.table');
    Route::resource('/admin/usuarios', AdminUserController::class);
    Route::resource('/admin/modulos', AdminModuleController::class)->parameters(['modulos' => 'module']);
    Route::post('/admin/videos/importar-playlist', [AdminVideoController::class, 'importPlaylist'])->name('videos.import-playlist');
    Route::post('/admin/videos/importar-video', [AdminVideoController::class, 'importVideo'])->name('videos.import-video');
    Route::resource('/admin/suscripciones', AdminSubscriptionController::class);
    Route::post('/admin/suscripciones/{subscription}/cancelar', [AdminSubscriptionController::class, 'cancelSubscription'])->name('subscriptions.cancel');
    Route::post('/admin/compras/{purchase}/cancelar', [AdminSubscriptionController::class, 'cancelPurchase'])->name('purchases.cancel');
    Route::get('/admin/{video}', [VideoController::class, 'showAdmin'])->name('admin-details');
});

Route::middleware('activePurchase')->group(function () {
    Route::get('/videos', [VideoController::class, 'indexUser'])->name('videos');
    Route::get('/videos/{video}', [VideoController::class, 'showUser'])->name('selectedVideo');
    Route::post('/videos/{video}/watched', [VideoController::class, 'updateWatched'])->name('videos.watched');
});

Route::middleware('auth')->group(function () {
    Route::get('/perfil', [ProfileController::class, 'index'])->name('profile');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::post('users/create', [UserController::class, 'createUser'])->name('guest.register');
Route::get('/{video}', [VideoController::class, 'showGuest'])->name('details');
