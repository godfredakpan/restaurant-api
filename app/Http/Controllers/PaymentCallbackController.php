<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{WalletTransaction, Shop, PaymentHistory};
use App\Services\VendorPayoutService;
use Unicodeveloper\Paystack\Facades\Paystack;

class PaymentCallbackController extends Controller
{
        public function handle(Request $request)
        {
            $paymentDetails = Paystack::getPaymentData();

            if (!$paymentDetails['status']) {
                return response()->json(['message' => 'Payment failed'], 400);
            }

            $metadata = $paymentDetails['data']['metadata'] ?? [];
            $reference = $paymentDetails['data']['reference'];

            \Log::info('Payment Metadata:', $metadata);

            if (($metadata['context'] ?? '') === 'order') {
                $shop = Shop::with(['admin.wallet'])->findOrFail($metadata['shop_id']);
                $commission = $metadata['commission'] ?? 0;
                $isFreePlan = $metadata['is_free_plan'] ?? false;

                $orderService = app(\App\Http\Controllers\OrderController::class);
                $order = $orderService->finalizeOrder(
                    $metadata['payload'],
                    $shop,
                    $metadata['user_id'],
                    $commission,
                    $reference
                );

                $totalPaid = $paymentDetails['data']['amount'] / 100;
                $platformFee = 20; // NGN 20 platform fee
                $vendorAmount = $totalPaid;
                $payoutStatus = 'failed';

                if ($isFreePlan) {
                    $walletBalance = $shop->admin->wallet->balance ?? 0;

                    if ($walletBalance >= $commission) {
                        // ✅ Deduct commission from wallet
                        $shop->admin->wallet->decrement('balance', $commission);
                        $vendorAmount = $totalPaid - $platformFee;

                        (new VendorPayoutService())->payVendor($order);
                        $payoutStatus = 'processing';
                    } else {
                        // ❌ Deduct commission and platform fee from payout
                        $vendorAmount = $totalPaid - $commission - $platformFee;

                        if ($vendorAmount < 0) {
                            $vendorAmount = 0;
                        }

                        (new VendorPayoutService())->payVendorFromPaystack($vendorAmount, $order);
                        $payoutStatus = 'processing';
                    }
                } else {
                    // ✅ Paid plan: deduct only platform fee
                    $vendorAmount = $totalPaid - $platformFee;

                    (new VendorPayoutService())->payVendor($order);
                    $payoutStatus = 'processing';
                }

                // 📝 Log payment history
                PaymentHistory::create([
                    'order_id' => $order->id,
                    'shop_id' => $shop->id,
                    'amount' => $vendorAmount,
                    'reference' => $reference,
                    'channel' => $paymentDetails['data']['channel'] ?? 'unknown',
                    'status' => $payoutStatus,
                ]);

                return redirect('https://www.orderrave.ng/stores/' . $shop->slug . '/order-confirmation?tid=' . $order->tracking_number);
            }


        // 💳 Handle Wallet top-up
        $transaction = WalletTransaction::where('reference', $reference)->first();
        if (!$transaction) {
            return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Transaction not found');
        }

        if ($transaction->status === 'completed') {
            return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Transaction already completed');
        }

        if ($paymentDetails['data']['status'] === 'success') {
            $amount = $paymentDetails['data']['amount'] / 100;
            $wallet = $transaction->wallet;

            $transaction->update([
                'status' => 'completed',
                'meta' => array_merge($transaction->meta ?? [], [
                    'gateway_response' => $paymentDetails['data']['gateway_response'],
                    'paid_at' => $paymentDetails['data']['paid_at'],
                    'payment_method' => $paymentDetails['data']['channel'] ?? null,
                ])
            ]);

            $wallet->deposit($amount, $reference);
            return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Wallet funded successfully');
        }

        $transaction->update(['status' => 'failed']);
        return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Payment failed');
    }
}
