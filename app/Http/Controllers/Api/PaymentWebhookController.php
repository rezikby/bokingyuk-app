<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * Handle Midtrans payment notification webhook.
     * This endpoint must be excluded from CSRF and auth middleware.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Payment webhook received', ['order_id' => $payload['order_id'] ?? null]);

        try {
            $this->paymentService->handleNotification($payload);

            return $this->success(null, 'Notification processed.');
        } catch (\InvalidArgumentException $e) {
            Log::warning('Invalid webhook signature', ['payload' => $payload]);

            return $this->error('Invalid signature.', 403);
        } catch (\Exception $e) {
            Log::error('Webhook processing error', ['error' => $e->getMessage()]);

            return $this->serverError('Failed to process notification.');
        }
    }
}
