<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\PaymentHistory;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();


        $event = $request->input('event');

        Log::info("📡 Paystack Webhook Event Received: $event");

        switch ($event) {
            case 'transfer.success':
                $data = $request->input('data');
                Log::info('✅ Transfer successful: ', $data);

                $reason = $data['reason'] ?? '';
                preg_match('/#(\d+)/', $reason, $matches);
                $orderId = $matches[1] ?? null;

                if ($orderId) {
                    $order = Order::find($orderId);
                    if ($order) {
                        $order->update(['payout_status' => 'completed']);

                        $paymentHistory = PaymentHistory::where('order_id', $order->id)
                            ->latest()
                            ->first();

                        if ($paymentHistory) {
                            $paymentHistory->update(['status' => 'completed']);
                            Log::info("✅ PaymentHistory updated to completed for Order #{$orderId}");
                        } else {
                            Log::warning("⚠️ No PaymentHistory found for Order #{$orderId}");
                        }
                    } else {
                        Log::warning("⚠️ Order not found for ID #{$orderId}");
                    }
                } else {
                    Log::warning("⚠️ Could not extract order ID from reason: $reason");
                }

                break;

            case 'transfer.failed':
                $data = $request->input('data');
                $reason = $data['reason'] ?? 'unknown';
                Log::warning("❌ Transfer failed: Reason - $reason", $data);

                // Optional: update failed status in PaymentHistory or queue retry
                break;

            case 'transfer.reversed':
                $data = $request->input('data');
                Log::warning("↩️ Transfer reversed: ", $data);

                // Optional: update status to reversed
                break;

            default:
                Log::info('ℹ️ Unhandled Paystack event: ' . $event);
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}
