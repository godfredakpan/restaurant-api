<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index()
    {
        return Subscription::with(['shop', 'user'])->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'user_id' => 'required|exists:users,id',
            'payment_plan' => 'required|string',
            'duration' => 'required|integer|min:1',
            'status' => 'required|string|in:pending,active,cancelled',
            'amount' => 'required|numeric|min:0',
        ]);
        
        $subscription = Subscription::updateOrCreate(
            [
                'shop_id' => $request->shop_id,
                'user_id' => $request->user_id,
            ],
            [
                'payment_plan' => $request->payment_plan,
                'duration' => $request->duration,
                'status' => $request->status,
                'amount' => $request->amount,
            ]
        );
        
        return response()->json($subscription, 201);
    }

    public function show($id)
    {
        $subscription = Subscription::with(['shop', 'user'])->findOrFail($id);

        return response()->json($subscription);
    }

    public function update(Request $request, $id)
    {
        $subscription = Subscription::findOrFail($id);

        $request->validate([
            'payment_plan' => 'sometimes|required|string',
            'duration' => 'sometimes|required|integer|min:1',
            'status' => 'sometimes|required|string|in:pending,active,cancelled',
            'amount' => 'sometimes|required|numeric|min:0',
        ]);

        $subscription->update($request->all());

        return response()->json($subscription);
    }

    public function destroy($id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->delete();

        return response()->json(['message' => 'Subscription deleted successfully.']);
    }


    public function createFreePlan($shop_id, $user_id)
    {
        $subscription = Subscription::updateOrCreate(
            [
                'shop_id' => $shop_id,
                'user_id' => $user_id,
            ],
            [
                'payment_plan' => 'free',
                'duration' => 999999,
                'status' => 'active',
                'amount' => 0,
            ]
        );
        
        return response()->json($subscription, 201);
    }
}
