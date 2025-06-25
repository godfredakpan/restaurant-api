<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FailedPayout;
use App\Services\VendorPayoutService;
use App\Models\PaymentHistory;

class RetryFailedPayouts extends Command
{
    protected $signature = 'payouts:retry';
    protected $description = 'Retry all unresolved failed vendor payouts';

    public function handle()
    {
        \Log::info('Starting retry of unresolved failed vendor payouts...');
        $failures = FailedPayout::where('resolved', false)->get();
        $payoutService = new VendorPayoutService();
        

        foreach ($failures as $failure) {
            try {
                \Log::info("Processing FailedPayout ID: {$failure->id}, Order ID: {$failure->order_id}");

                $order = $failure->order;
                \Log::info("Order details", [
                    'order_id' => $order->id,
                    'shop_id' => $order->shop->id,
                    'net_amount' => $order->net_amount,
                    'payment_reference' => $order->payment_reference,
                ]);

                $payoutService->payVendor($order);
                \Log::info("Payout attempted for order #{$order->id}");

                $failure->update(['resolved' => true]);
                \Log::info("Marked FailedPayout ID {$failure->id} as resolved.");

                PaymentHistory::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'shop_id' => $order->shop->id,
                        'amount' => $order->net_amount,
                        'reference' => $order->payment_reference,
                        'channel' => 'paystack',
                        'status' => 'success',
                    ]
                );
                \Log::info("PaymentHistory updated/created for order #{$order->id}, amount: ₦{$order->net_amount}, shop ID: {$order->shop->id}");

                $this->info("Retried payout for order #{$order->id}");

            } catch (\Exception $e) {
                \Log::error("Retry failed for order #{$failure->order_id}: " . $e->getMessage(), [
                    'exception' => $e,
                    'failed_payout_id' => $failure->id,
                ]);
            }
        }
        \Log::info('Retry of unresolved failed vendor payouts completed.');
    }
}