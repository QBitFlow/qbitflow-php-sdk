<?php

/**
 * routes/api.php (or routes/web.php, excluded from CSRF protection).
 */

declare(strict_types=1);

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

// POST /webhooks/qbitflow: verified with QBITFLOW_WEBHOOK_SECRET, answered 200, and turned into
// Laravel events (PaymentCompleted, SubscriptionStatusChanged, … and WebhookReceived).
Route::qbitflowWebhooks('webhooks/qbitflow');

Route::post('/checkout/{order}', [CheckoutController::class, 'store']);
