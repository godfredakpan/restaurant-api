<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\PaymentHistory;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Unicodeveloper\Paystack\Facades\Paystack;

class WalletController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $wallet = $user->wallet()->firstOrCreate([
            'user_id' => $user->id
        ], [
            'balance' => 0,
            'currency' => 'NGN'
        ]);

        $transactions = $wallet->transactions()->latest()->get();

        // Fetch payment history for this shop (assuming 1:1 user-shop relation)
         $paymentHistory = PaymentHistory::with(['order:id,order_number,user_name,order_type,table_number'])
            ->where('shop_id', $user->shop_id)
            ->select('id', 'order_id', 'amount', 'status', 'channel', 'created_at')
            ->latest()
        ->get();

        return response()->json([
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
            'transactions' => $transactions,
            'payment_history' => $paymentHistory,
        ]);
    }

    public function deposit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100'
        ]);

        $reference = Paystack::genTranxRef();
        $amount = $request->amount * 100; // Paystack uses kobo

        $wallet = Auth::user()->wallet()->firstOrCreate([
            'user_id' => Auth::id()
        ], [
            'balance' => 0,
            'currency' => 'NGN'
        ]);

        // Create a pending transaction
        $transaction = $wallet->transactions()->create([
            'amount' => $request->amount,
            'type' => 'credit',
            'status' => 'pending',
            'description' => 'Wallet funding',
            'reference' => $reference,
            'meta' => [
                'gateway' => 'paystack'
            ]
        ]);

        $paymentData = [
            'amount' => $amount,
            'email' => Auth::user()->email,
            'reference' => $reference,
            'currency' => 'NGN',
            'metadata' => [
                'transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
                'user_id' => Auth::id()
            ]
        ];

        return response()->json([
            'authorization_url' => Paystack::getAuthorizationUrl($paymentData)->url
        ]);
    }

    // public function handlePaymentCallback(Request $request)
    // {
    //     $paymentDetails = Paystack::getPaymentData();

    //     if (!$paymentDetails['status']) {
    //         return response()->json(['message' => 'Payment failed'], 400);
    //     }

    //     $metadata = $paymentDetails['data']['metadata'];
    //     $reference = $paymentDetails['data']['reference'];

    //     if (($metadata['context'] ?? '') === 'order') {
    //         $shop = Shop::with(['admin.wallet'])->findOrFail($metadata['shop_id']);
    //         $commission = $metadata['commission'];

    //         $order = $this->finalizeOrder($metadata['payload'], $shop, $metadata['user_id'], $commission);

    //         if ($metadata['is_free_plan']) {
    //             $walletBalance = $shop->admin->wallet->balance ?? 0;
    //             if ($walletBalance >= $commission) {
    //                 $shop->admin->wallet->decrement('balance', $commission);
    //             } else {
    //                 (new VendorPayoutService())->payVendor($order);
    //             }
    //         }

    //         PaymentHistory::create([
    //             'order_id' => $order->id,
    //             'vendor_id' => $shop->admin->id,
    //             'amount' => $paymentDetails['data']['amount'] / 100,
    //             'reference' => $reference,
    //             'channel' => $paymentDetails['data']['channel'] ?? 'unknown',
    //             'status' => 'success',
    //         ]);

    //         return redirect('https://app.orderrave.ng/orders/success?ref=' . $reference);
    //     } else {
    //         // fallback: treat as wallet funding (existing logic)
    //         $transaction = WalletTransaction::where('reference', $reference)->first();

    //         if (!$transaction) {
    //             return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Transaction not found');
    //         }

    //         if ($transaction->status === 'completed') {
    //             return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Transaction already completed');
    //         }

    //         if ($paymentDetails['data']['status'] === 'success') {
    //             $amount = $paymentDetails['data']['amount'] / 100;
    //             $wallet = $transaction->wallet;

    //             $transaction->update([
    //                 'status' => 'completed',
    //                 'meta' => array_merge($transaction->meta ?? [], [
    //                     'gateway_response' => $paymentDetails['data']['gateway_response'],
    //                     'paid_at' => $paymentDetails['data']['paid_at'],
    //                     'payment_method' => $paymentDetails['data']['channel'] ?? null,
    //                 ])
    //             ]);

    //             $wallet->deposit($amount, $reference);

    //             return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Wallet funded successfully');
    //         }

    //         $transaction->update([
    //             'status' => 'failed',
    //             'reference' => $reference
    //         ]);
    //         return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Payment failed');
    //     }
    // }


    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100'
        ]);

        $wallet = Auth::user()->wallet()->firstOrFail();

        try {
            $wallet->withdraw($request->amount);
            
            return response()->json([
                'message' => 'Withdrawal successful',
                'balance' => $wallet->fresh()->balance
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}