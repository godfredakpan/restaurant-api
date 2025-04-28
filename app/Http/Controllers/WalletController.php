<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Unicodeveloper\Paystack\Facades\Paystack;

class WalletController extends Controller
{
    public function index()
    {
        $wallet = Auth::user()->wallet()->firstOrCreate([
            'user_id' => Auth::id()
        ], [
            'balance' => 0,
            'currency' => 'NGN'
        ]);

        return response()->json([
            'balance' => $wallet->balance,
            'currency' => $wallet->currency,
            'transactions' => $wallet->transactions()->latest()->get()
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

    public function handlePaymentCallback()
    {
        $paymentDetails = Paystack::getPaymentData();

        if (!$paymentDetails['status']) {
            return response()->json(['message' => 'Payment failed'], 400);
        }

        $reference = $paymentDetails['data']['reference'];
        $transaction = WalletTransaction::where('reference', $reference)->first();

        if (!$transaction) {
            return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Transaction not found');
            // return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($transaction->status === 'completed') {
            return redirect('https://app.orderrave.ng/settings/wallet')->with('message', 'Transaction already completed');
            // return response()->json(['message' => 'Transaction already processed']);
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

        $transaction->update([
            'status' => 'failed',
            'reference' => $reference // Set reference even for failed transactions
        ]);
        return response()->json(['message' => 'Payment failed'], 400);
    }


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