<?php

// app/Http/Controllers/RatingController.php
namespace App\Http\Controllers;

use App\Models\Rating;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RatingController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 202);
        }

        if ((string) $order->user_id !== (string) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 202);
        }

        if ($order->order_status !== 'confirmed') {
            return response()->json(['message' => 'Order must be completed to rate'], 202);
        }

        // Check if rating already exists
        if (Rating::where('order_id', $order->id)->where('user_id', Auth::id())->exists()) {
            return response()->json(['message' => 'You have already rated this order'], 202);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:500'
        ]);

        $rating = Rating::create([
            'user_id' => Auth::id(),
            'shop_id' => $order->shop_id,
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null
        ]);

        // Update shop's average rating
        $this->updateShopRating($order->shop);

        return response()->json($rating, 200);
    }

    public function index(Shop $shop)
    {
        $ratings = $shop->ratings()
            ->with('user:id,name')
            ->latest()
            ->paginate(10);

        return response()->json([
            'ratings' => $ratings,
            'average' => $shop->average_rating,
            'count' => $shop->ratings_count
        ]);
    }

    public function show(Rating $rating)
    {
        return response()->json($rating->load('user:id,name'));
    }

    public function update(Request $request, Rating $rating)
    {
        if ($rating->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'rating' => 'sometimes|integer|between:1,5',
            'comment' => 'nullable|string|max:500'
        ]);

        $rating->update($validated);

        // Update shop's average rating
        $this->updateShopRating($rating->shop);

        return response()->json($rating);
    }

    protected function updateShopRating(Shop $shop)
    {
        $shop->update([
            'average_rating' => $shop->ratings()->avg('rating'),
            'ratings_count' => $shop->ratings()->count()
        ]);
    }
}
