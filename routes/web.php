<?php

declare(strict_types=1);

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;
use TransistorizedCmd\StripeToolkit\Webhooks\Facades\StripeWebhook;

// Demo storefront — exercises the full Stripe Checkout → webhook flow.
Route::get('/', [CheckoutController::class, 'landing'])->name('checkout.landing');
Route::post('/checkout', [CheckoutController::class, 'start'])->name('checkout.start');
Route::get('/orders', [CheckoutController::class, 'index'])->name('checkout.index');
Route::get('/orders/{order}', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/orders/{order}/cancelled', [CheckoutController::class, 'cancelled'])->name('checkout.cancelled');
Route::get('/orders/{order}/status', [CheckoutController::class, 'status'])->name('checkout.status');
Route::post('/orders/{order}/reconcile', [CheckoutController::class, 'reconcile'])->name('checkout.reconcile');
Route::post('/demo/reset', [CheckoutController::class, 'reset'])->name('demo.reset');

// Stripe webhook endpoints — verified by the kit.
StripeWebhook::route('stripe/webhook');
StripeWebhook::route('stripe/webhook/{configKey}');
