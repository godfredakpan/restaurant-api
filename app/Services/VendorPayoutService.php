<?php

namespace App\Services;

use App\Models\Order;
use App\Models\FailedPayout;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VendorPayoutService
{
    public function payVendor(Order $order)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secretKey'),
            'Accept' => 'application/json',
        ])->get('https://api.paystack.co/balance');

        Log::info('Current Paystack balance:', $response->json());

        Log::info("Starting vendor payout for Order #{$order->id}");

        $this->ensureRecipient($order->shop);

        $amountKobo = round($order->net_amount * 100);
        Log::info("Transferring ₦{$order->net_amount} to vendor [{$order->shop->id}] using recipient code: {$order->shop->paystack_recipient_code}");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secretKey'),
            'Accept' => 'application/json',
        ])->post('https://api.paystack.co/transfer', [
            'source' => 'balance',
            'amount' => $amountKobo,
            'recipient' => $order->shop->paystack_recipient_code,
            'reason' => 'Order payout for #' . $order->id,
        ]);

        if ($response->successful()) {
            Log::info("Payout initiated successfully for Order #{$order->id}, amount ₦{$order->net_amount}");
            $order->update(['payout_status' => 'processing']);
        } else {
            $error = $response->json('message') ?? 'Unknown failure';

             FailedPayout::updateOrCreate(
                ['order_id' => $order->id, 'resolved' => false],
                [
                    'shop_id' => $order->shop->id,
                    'amount' => $order->net_amount,
                    'reason' => $error,
                    'channel' => 'paystack',
                    'status' => 'failed',
                ]
            );
            Log::error("Payout failed for Order #{$order->id}. Response: " . $response->body());
        }
    }

    public function payVendorFromPaystack(float $amount, Order $order)
    {
        Log::info("Fallback payout from Paystack for Order #{$order->id}. Vendor share: ₦{$amount}");

        $this->ensureRecipient($order->shop);

        $amountKobo = round($amount * 100);
        Log::info("Initiating Paystack fallback payout of ₦{$amount} to vendor [{$order->shop->id}] with recipient code: {$order->shop->paystack_recipient_code}");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('paystack.secretKey'),
            'Accept' => 'application/json',
        ])->post('https://api.paystack.co/transfer', [
            'source' => 'balance',
            'amount' => $amountKobo,
            'recipient' => $order->shop->paystack_recipient_code,
            'reason' => 'Adjusted payout for #' . $order->id . ' (wallet insufficient)',
        ]);

        if ($response->successful()) {
            Log::info("Fallback payout successful for Order #{$order->id}, paid ₦{$amount}");
            $order->update(['payout_status' => 'processing']);
        } else {
            $error = $response->json('message') ?? 'Unknown failure';

            // minus platform fee
            $amount -= 20;
            
            FailedPayout::updateOrCreate(
                    ['order_id' => $order->id, 'resolved' => false],
                    [
                        'shop_id' => $order->shop->id,
                        'amount' => $amount,
                        'reason' => $error,
                        'channel' => 'paystack',
                        'status' => 'failed',
                    ]
                );
            
            Log::error("Fallback payout failed for Order #{$order->id}. Response: " . $response->body());
        }
    }

    protected function ensureRecipient($vendor)
    {
        if (!$vendor->paystack_recipient_code) {
            Log::info("Creating new Paystack recipient for vendor [{$vendor->id}]");

            $payload = [
                'type' => 'nuban',
                'name' => $vendor->shop_name,
                'account_number' => $vendor->account_number,
                'bank_code' => $vendor->account_bank_code,
                'currency' => 'NGN',
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('paystack.secretKey'),
                'Accept' => 'application/json',
            ])->post('https://api.paystack.co/transferrecipient', $payload);

            if ($response->successful()) {
                $recipient = $response->json('data');
                $vendor->paystack_recipient_code = $recipient['recipient_code'];
                $vendor->save();

                Log::info("Recipient created successfully for vendor [{$vendor->id}] with code: {$recipient['recipient_code']}");
            } else {
                Log::error("Failed to create Paystack recipient for vendor [{$vendor->id}]. Response: " . $response->body());
                throw new \Exception('Unable to create Paystack recipient');
            }
        } else {
            Log::info("Vendor [{$vendor->id}] already has recipient code: {$vendor->paystack_recipient_code}");
        }
    }
}
