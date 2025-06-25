<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        // Optional: verify signature here if needed

        $event = $request->input('event');

        Log::info("📡 Paystack Webhook Event Received: $event");

        switch ($event) {
            case 'transfer.success':
                Log::info('✅ Transfer successful: ', $request->all());
                break;

            case 'transfer.failed':
                Log::warning('❌ Transfer failed: ', $request->all());
                // Optionally: queue retry logic or alert admins
                break;

            case 'transfer.reversed':
                Log::warning('↩️ Transfer reversed: ', $request->all());
                break;

            // Handle more events as needed
            default:
                Log::info('Unhandled Paystack event: ' . $event);
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}
