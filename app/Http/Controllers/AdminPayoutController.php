<?php

namespace App\Http\Controllers;

use App\Models\FailedPayout;
use App\Services\VendorPayoutService;
use App\Models\PaymentHistory;

class AdminPayoutController extends Controller
{
    public function index()
        {
            return FailedPayout::with([
                'order:id,order_number,order_type,order_status,user_name,additional_notes,table_number,commission,net_amount,created_at',
                'shop:id,shop_name,email'
            ])->get([
                'id', 'order_id', 'shop_id', 'reason', 'amount', 'resolved', 'created_at'
            ]);
        }


   public function paymentHistory()
    {
        return PaymentHistory::with([
            'order:id,order_number,order_type,order_status,user_name,additional_notes,table_number,commission,net_amount,created_at',
            'shop:id,shop_name,email'
        ])
        ->select('id', 'order_id', 'shop_id', 'reference', 'amount', 'channel', 'status', 'created_at')
        ->orderByDesc('created_at')
        ->get();
    }


    public function retry($id)
    {
        $failed = FailedPayout::with('order.shop.admin')->findOrFail($id);
        $service = new VendorPayoutService();
        $service->payVendor($failed->order);

        $failed->update(['resolved' => true]);

        // Find existing payment history
        $history = PaymentHistory::where('order_id', $failed->order->id)->first();

        if ($history) {
            $history->update([
                'shop_id' => $failed->order->shop->id,
                'amount' => $failed->amount,
                'channel' => 'paystack',
                'status' => 'success',
            ]);
        } else {
            // Only create if it does not already exist
            PaymentHistory::create([
                'order_id' => $failed->order->id,
                'shop_id' => $failed->order->shop->id,
                'amount' => $failed->amount,
                'channel' => 'paystack',
                'status' => 'success',
            ]);
        }

        return response()->json(['success' => true]);
    }

}
