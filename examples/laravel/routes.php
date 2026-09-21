<?php

/**
 * Webhook routes, for routes/api.php.
 *
 * Both macros register a POST route already wrapped in signature verification. They also
 * answer the dashboard's "Test the endpoint" probe automatically, so it never reaches your
 * listeners.
 *
 * Keep these in routes/api.php. If you move them to routes/web.php, exclude the paths from
 * CSRF protection — QBitFlow does not send a CSRF token.
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::qbitflowTransactionWebhook('/webhooks/qbitflow/transaction');
Route::qbitflowSubscriptionWebhook('/webhooks/qbitflow/subscription');

// Verification is also available as a middleware alias, for a route of your own:
//
// Route::post('/hooks/qbitflow', MyWebhookController::class)->middleware('qbitflow.webhook');
