<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\VideoController;
use App\Http\Controllers\PlanSelectionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AccessController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ProfileController;
use MercadoPago\Client\PreApproval\PreApprovalClient;

Route::get('/', [VideoController::class, 'indexGuest'])->name('index');
Route::get('/plan', [PlanSelectionController::class, 'index'])->name('plan-selection');

Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout/confirmation', [CheckoutController::class, 'showCheckout'])->name('checkout-confirmation');
Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
Route::get('/payment/{purchase}/{provider}', [PaymentController::class, 'start'])->name('payment.start');
Route::get('/subscription/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/subscription/failure', [PaymentController::class, 'failure'])->name('payment.failure');
Route::get('/subscription/pending', [PaymentController::class, 'pending'])->name('payment.pending');

Route::post('/webhooks/mercadopago', [WebhookController::class, 'handleMercadoPagoWebhook']);
Route::post('/payment/paypal-webhook', [WebhookController::class, 'handlePayPalWebhook'])->name('payment.paypal-webhook');

Route::get('/login', [AuthController::class, 'showLogin'])->name('showLogin');
Route::post('/login', [AuthController::class, 'login'])->name('login');



Route::get('/test-preapproval/{id}', function ($id) {

    $client = new PreApprovalClient();

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
    Route::get('/admin/videos', [AdminController::class, 'videosTable'])->name('videos.table');
    Route::resource('/admin/usuarios', AdminUserController::class);
    // Route::get('/admin/usuarios', [AdminController::class, 'usersTable'])->name('users.table');
    // Route::get('/admin/usuarios/creacion', [AdminController::class, 'userCreate'])->name('user.create');
    // Route::post('/admin/usuarios/store', [AdminController::class, 'userStore'])->name('user.store');
    // Route::get('/admin/usuarios/editar/{user}', [AdminController::class, 'userEdit'])->name('user.edit');
    // Route::put('/admin/usuarios/update/{user}', [AdminController::class, 'userUpdate'])->name('user.update');
    // Route::delete('/admin/usuarios/destroy/{user}', [AdminController::class, 'userDestroy'])->name('user.destroy');
    // Route::get('/admin/usuarios/{user}', [AdminController::class, 'userShow'])->name('user.show');
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