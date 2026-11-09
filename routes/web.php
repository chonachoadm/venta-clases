<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminModuleController;
use App\Http\Controllers\AdminSubscriptionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminVideoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PasswordSetupController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VideoController::class, 'indexGuest'])->name('index');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::middleware('throttle:checkout')->group(function () {
    Route::post('/checkout/confirmation', [CheckoutController::class, 'showCheckout'])->name('checkout.confirmation');
    Route::post('/checkout/mercadopago/suscripcion', [CheckoutController::class, 'payWithMercadoPagoSubscription'])->name('checkout.mercadopago.subscription');
    Route::post('/checkout/mercadopago/pago-unico', [CheckoutController::class, 'payWithMercadoPagoOneTime'])->name('checkout.mercadopago.onetime');
    Route::post('/checkout/paypal/suscripcion', [CheckoutController::class, 'payWithPayPalSubscription'])->name('checkout.paypal.subscription');
    Route::post('/checkout/paypal/pago-unico', [CheckoutController::class, 'payWithPayPalOneTime'])->name('checkout.paypal.onetime');
});
Route::get('/subscription/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/subscription/failure', [PaymentController::class, 'failure'])->name('payment.failure');
Route::get('/subscription/pending', [PaymentController::class, 'pending'])->name('payment.pending');

Route::post('/webhooks/mercadopago', [WebhookController::class, 'handleMercadoPagoWebhook'])->name('webhooks.mercadopago');
Route::post('/payment/paypal-webhook', [WebhookController::class, 'handlePayPalWebhook'])->name('payment.paypal-webhook');

Route::get('/login', [AuthController::class, 'showLogin'])->name('showLogin');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

Route::get('/contrasena/olvide', [PasswordSetupController::class, 'showForgotForm'])->name('password.request');
Route::post('/contrasena/email', [PasswordSetupController::class, 'sendResetLink'])->middleware('throttle:login')->name('password.email');
Route::get('/contrasena/restablecer/{token}', [PasswordSetupController::class, 'showResetForm'])->name('password.reset');
Route::post('/contrasena/restablecer', [PasswordSetupController::class, 'reset'])->middleware('throttle:login')->name('password.update');

Route::middleware('admin')->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('dashboard');
    Route::post('/admin/exchange-rate', [AdminController::class, 'updateExchangeRate'])->name('exchange-rate.update');
    Route::get('/admin/videos', [AdminController::class, 'videosTable'])->name('videos.table');
    Route::resource('/admin/usuarios', AdminUserController::class);
    Route::resource('/admin/modulos', AdminModuleController::class)->parameters(['modulos' => 'module']);
    Route::post('/admin/videos/importar-playlist', [AdminVideoController::class, 'importPlaylist'])->name('videos.import-playlist');
    Route::post('/admin/videos/importar-video', [AdminVideoController::class, 'importVideo'])->name('videos.import-video');
    Route::resource('/admin/suscripciones', AdminSubscriptionController::class)->only(['index']);
    Route::post('/admin/suscripciones/{subscription}/cancelar', [AdminSubscriptionController::class, 'cancelSubscription'])->name('subscriptions.cancel');
    Route::post('/admin/compras/{purchase}/cancelar', [AdminSubscriptionController::class, 'cancelPurchase'])->name('purchases.cancel');
    Route::get('/admin/{video}', [VideoController::class, 'showAdmin'])->whereNumber('video')->name('admin-details');
});

Route::middleware('activePurchase')->group(function () {
    Route::get('/videos', [VideoController::class, 'indexUser'])->name('videos');
    Route::get('/videos/{video}', [VideoController::class, 'showUser'])->whereNumber('video')->name('selectedVideo');
    Route::post('/videos/{video}/watched', [VideoController::class, 'updateWatched'])->whereNumber('video')->name('videos.watched');
});

Route::middleware('auth')->group(function () {
    Route::get('/perfil', [ProfileController::class, 'index'])->name('profile');
    Route::get('/cambiar-contrasena', [PasswordSetupController::class, 'showChangeForm'])->name('password.change');
    Route::post('/cambiar-contrasena', [PasswordSetupController::class, 'change'])->middleware('throttle:login')->name('password.change.update');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/{video}', [VideoController::class, 'showGuest'])->whereNumber('video')->name('details');
