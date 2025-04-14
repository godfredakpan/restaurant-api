<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Fetch recent transactions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecentTransactions()
    {
        // Get recent transactions from the orders table or another related table
        $user = auth()->user();
        $transactions = DB::table('orders')
            ->where('shop_id', $user->shop_id)
            ->select(
                'created_at',
                'order_total',
                'user_name',
                'id',
                'order_status'
            )
            ->orderBy('created_at', 'desc')
            ->limit(6) 
            ->get();

        // Format the data to match the view requirements
        $formattedTransactions = $transactions->map(function ($transaction) {
            return [
                'created_at' => $transaction->created_at,
                'description' => $this->getTransactionDescription($transaction),
                'order_id' =>   "#" . "Order" . $transaction->id, // Assuming the 'id' is available for each order
                'order_total' => $transaction->order_total,
                'order_status' => $this->getTransactionStatus($transaction),
                
            ];
        });

        return response()->json($formattedTransactions);
    }

   

    /**
     * Get the description of the transaction.
     *
     * @param  \stdClass  $transaction
     * @return string
     */
    private function getTransactionDescription($transaction)
    {
        // Return description based on the transaction's order status
        switch ($transaction->order_status) {
            case 'paid':
                return "Payment received from " . $transaction->user_name . 
                    " of ₦" . number_format($transaction->order_total, 2);
            
            case 'pending':
                return "Order from " . $transaction->user_name . 
                    " is pending. Total amount: ₦" . number_format($transaction->order_total, 2);
            
            case 'cancelled':
                return "Order by " . $transaction->user_name . 
                    " was cancelled. Total amount: ₦" . number_format($transaction->order_total, 2);
            
            case 'refunded':
                return "Refund of ₦" . number_format($transaction->order_total, 2) . 
                    " issued to " . $transaction->user_name;

            case 'processing':
                return "Order from " . $transaction->user_name . 
                    " is being processed. Total: ₦" . number_format($transaction->order_total, 2);
            
            case 'completed':
                return "Order by " . $transaction->user_name . 
                    " has been completed. Total: ₦" . number_format($transaction->order_total, 2);
            
            default:
                return "New sale recorded for ₦" . number_format($transaction->order_total, 2);
        }
    }


    /**
     * Get the status (color indicator) for the transaction.
     *
     * @param  \stdClass  $transaction
     * @return string
     */
    private function getTransactionStatus($transaction)
    {
        // Example statuses based on order status
        switch ($transaction->order_status) {
            case 'confirmed':
                return 'success';
            case 'pending':
                return 'warning';
            case 'rejected':
                return 'error';
            default:
                return 'primary';
        }
    }
}
