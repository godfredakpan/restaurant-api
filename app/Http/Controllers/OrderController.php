<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Shop;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\QRCodeOrder;
use App\Models\Subscription;
use App\Models\PromoCampaign;
use App\Models\PromoUse;
use App\Models\PaymentHistory;

use App\Services\VendorPayoutService;


use Illuminate\Support\Str;
use Unicodeveloper\Paystack\Facades\Paystack;



class OrderController extends Controller
{


    public function createOrder(Request $request)
    {
        // Validate request data first
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'type' => 'required|string|in:table,delivery,room',
            'name' => 'required|string',
            'shopId' => 'required|exists:shops,id',
            'tableNumber' => 'nullable|string',
            'hotelRoom' => 'nullable|string',
            'note' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'paymentStatus' => 'nullable|string',
            'email' => 'nullable|email',
            'promo_code' => 'nullable|string|exists:promo_campaigns,promo_code',
        ]);

        $shop = Shop::with(['admin.wallet', 'subscription'])
                    ->find($validated['shopId']);
                    
        if (!$shop) {
            return response()->json(['message' => 'Shop not found.', 'status' => 400], 400);
        }

        // Check delivery order login requirement
        if ($validated['type'] === 'delivery' && !$request->user_id) {
            return response()->json(['message' => 'Login required for delivery orders.', 'status' => 400], 400);
        }

        // Calculate order total and check promo code
        $orderTotal = 0;
        $discountAmount = 0;
        $itemDetails = '';
        $promoCampaign = null;

        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::find($item['menu_item_id']);
            $orderTotal += $menuItem->price * $item['quantity'];
            $itemDetails .= "- {$menuItem->name} (Quantity: {$item['quantity']}, Price: ₦{$menuItem->price})\n";
        }

        // Validate and apply promo code if provided
        if (!empty($validated['promo_code'])) {
            $promoCampaign = PromoCampaign::where('promo_code', $validated['promo_code'])
                ->where('shop_id', $shop->id)
                ->where('is_active', true)
                ->first();

            if ($promoCampaign) {
                // Check validity dates
                $now = now();
                if ($promoCampaign->start_date && $now->lt($promoCampaign->start_date)) {
                    return response()->json(['message' => 'Promo code is not valid yet.', 'status' => 400], 400);
                }
                
                if ($promoCampaign->end_date && $now->gt($promoCampaign->end_date)) {
                    return response()->json(['message' => 'Promo code has expired.', 'status' => 400], 400);
                }

                // Check usage limit
                if ($promoCampaign->usage_limit && $promoCampaign->times_used >= $promoCampaign->usage_limit) {
                    return response()->json(['message' => 'Promo code has reached its usage limit.', 'status' => 400], 400);
                }

                // Check valid days
                if ($promoCampaign->valid_days && !in_array($now->dayOfWeek, $promoCampaign->valid_days)) {
                    return response()->json(['message' => 'Promo code not valid today.', 'status' => 400], 400);
                }

                // Check valid times
                if ($promoCampaign->start_time && $promoCampaign->end_time) {
                    $currentTime = $now->format('H:i:s');
                    if ($currentTime < $promoCampaign->start_time || $currentTime > $promoCampaign->end_time) {
                        return response()->json(['message' => 'Promo code not valid at this time.', 'status' => 400], 400);
                    }
                }

                // Apply discount
                switch ($promoCampaign->type) {
                    case 'percentage':
                        $discountAmount = $orderTotal * ($promoCampaign->discount_value / 100);
                        break;
                    case 'fixed':
                        $discountAmount = min($promoCampaign->discount_value, $orderTotal);
                        break;
                    case 'bogo':
                        // Implement BOGO logic based on your business rules
                        // This is a simplified version - adjust as needed
                        $discountAmount = $this->calculateBogoDiscount($validated['items'], $promoCampaign);
                        break;
                }

                $orderTotal -= $discountAmount;
            } else {
                return response()->json(['message' => 'Invalid promo code.', 'status' => 400], 400);
            }
        }

        // Check wallet balance for free subscription shops
        if ($this->hasFreeSubscription($shop)) {
            $requiredBalance = $orderTotal * 0.05;
            $walletBalance = $shop->admin->wallet->balance ?? 0;
            
            if ($walletBalance < $requiredBalance) {
                return response()->json([
                    'message' => 'Shop wallet has insufficient funds (₦'.$walletBalance.') to cover the ₦'.$requiredBalance.' processing fee.',
                    'status' => 400
                ], 400);
            }
        }

        // Generate tracking ID
        $trackingId = Str::random(12);

        // Create the order
        $order = Order::create([
            'user_id' => $request->user_id,
            'shop_id' => $validated['shopId'],
            'order_number' => strtoupper(uniqid('ORD-')),
            'order_status' => 'pending',
            'order_type' => $validated['type'],
            'order_total' => $orderTotal + $discountAmount, 
            'discount_amount' => $discountAmount,
            'net_total' => $orderTotal,
            'commission' => $this->hasFreeSubscription($shop) ? $orderTotal * 0.05 : 0,
            'net_amount' => $this->hasFreeSubscription($shop) ? $orderTotal * 0.95 : $orderTotal,
            'additional_notes' => $validated['note'] ?? '',
            'address' => $validated['address'],
            'user_phone' => $validated['phone'],
            'user_name' => $validated['name'],
            'table_number' => $validated['tableNumber'],
            'hotel_room' => $validated['hotelRoom'],
            'tracking_number' => $trackingId,
            'commission_processed' => !$this->hasFreeSubscription($shop),
            'payment_status' => $request->paymentStatus ?? '',
            'promo_code' => $validated['promo_code'] ?? null,
            'promo_campaign_id' => $promoCampaign ? $promoCampaign->id : null,
        ]);

        // Add order items
        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'menu_item_id' => $item['menu_item_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        // Increment promo code usage if applied
        if ($promoCampaign) {
            $promoCampaign->increment('times_used');
            PromoUse::create([
                'promo_campaign_id' => $promoCampaign->id,
                'order_id' => $order->id,
                'promo_code' => $promoCampaign->promo_code,
                'shop_id' => $shop->id,
                'user_id' => $request->user_id ?? null,
            ]);
        }

        // Process commission immediately for paid orders
        if ($request->paymentStatus === 'paid' && $this->hasFreeSubscription($shop)) {
            $this->deductCommission($shop->admin->id, $orderTotal * 0.05, $order);
        }

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => [
                'orderDetails' => $order,
                'items' => $itemDetails,
                'shop_name' => $shop->shop_name,
            ],
            'trackingId' => $trackingId,
            'discountApplied' => $discountAmount > 0,
            'discountAmount' => $discountAmount,
        ], 201);
    }

    public function createOrderV2(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'type' => 'required|string|in:table,delivery,room',
            'name' => 'required|string',
            'shopId' => 'required|exists:shops,id',
            'tableNumber' => 'nullable|string',
            'hotelRoom' => 'nullable|string',
            'note' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'paymentStatus' => 'nullable|string',
            'email' => 'nullable|email',
            'promo_code' => 'nullable|string|exists:promo_campaigns,promo_code',
        ]);

        $shop = Shop::with(['admin.wallet', 'subscription'])->find($validated['shopId']);
        if (!$shop) return response()->json(['message' => 'Shop not found.', 'status' => 400], 400);

        if ($validated['type'] === 'delivery' && !$request->user_id) {
            return response()->json(['message' => 'Login required for delivery orders.', 'status' => 400], 400);
        }

        $orderTotal = 0;
        $discountAmount = 0;
        $promoCampaign = null;

        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::find($item['menu_item_id']);
            $orderTotal += $menuItem->price * $item['quantity'];
        }

        if (!empty($validated['promo_code'])) {
            $promoCampaign = PromoCampaign::where('promo_code', $validated['promo_code'])
                ->where('shop_id', $shop->id)->where('is_active', true)->first();

            if (!$promoCampaign) return response()->json(['message' => 'Invalid promo code.', 'status' => 400], 400);

            $now = now();
            if (($promoCampaign->start_date && $now->lt($promoCampaign->start_date)) ||
                ($promoCampaign->end_date && $now->gt($promoCampaign->end_date))) {
                return response()->json(['message' => 'Promo code not valid now.', 'status' => 400], 400);
            }

            switch ($promoCampaign->type) {
                case 'percentage':
                    $discountAmount = $orderTotal * ($promoCampaign->discount_value / 100);
                    break;
                case 'fixed':
                    $discountAmount = min($promoCampaign->discount_value, $orderTotal);
                    break;
                case 'bogo':
                    $discountAmount = $this->calculateBogoDiscount($validated['items'], $promoCampaign);
                    break;
            }

            $orderTotal -= $discountAmount;
        }

        $isFreePlan = $this->hasFreeSubscription($shop);
        $commission = $isFreePlan ? $orderTotal * 0.05 : 0;

        $reference = 'ORD-' . strtoupper(Str::random(10));
        $callbackUrl = route('paystack.callback');

        $orderTotal += 20; // Platform fee

        $paymentRequest = Paystack::getAuthorizationUrl([
            'amount' => round(($orderTotal + $discountAmount) * 100),
            'email' => $validated['email'] ?? 'customer@orderrave.ng',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => [
                'shop_id' => $shop->id,
                'commission' => $commission,
                'is_free_plan' => $isFreePlan,
                'payload' => $validated,
                'user_id' => $request->user_id ?? null,
                'context' => 'order'
            ]
        ])->url;

        return response()->json(['paystack_url' => $paymentRequest], 200);
    }

    public function finalizeOrder($validated, $shop, $userId, $commission, $reference)
    {
        $orderTotal = 0;
        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::find($item['menu_item_id']);
            $orderTotal += $menuItem->price * $item['quantity'];
        }

        $trackingId = Str::random(12);
        $order = Order::create([
            'user_id' => $userId,
            'shop_id' => $validated['shopId'],
            'order_number' => strtoupper(uniqid('ORD-')),
            'order_status' => 'completed',
            'order_type' => $validated['type'],
            'order_total' => $orderTotal,
            'discount_amount' => 0,
            'net_total' => $orderTotal,
            'commission' => $commission,
            'net_amount' => $orderTotal - $commission,
            'additional_notes' => $validated['note'] ?? '',
            'address' => $validated['address'],
            'user_phone' => $validated['phone'],
            'user_name' => $validated['name'],
            'table_number' => $validated['tableNumber'],
            'hotel_room' => $validated['hotelRoom'],
            'tracking_number' => $trackingId,
            'commission_processed' => true,
            'payment_status' => 'paid',
            'payment_reference' => $reference,
        ]);

        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'menu_item_id' => $item['menu_item_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        return $order;
    }


    private function calculateBogoDiscount($items, $promoCampaign)
    {
        // Implement your BOGO logic here
        // For example: Buy one get one free on the cheapest item
        $prices = [];
        foreach ($items as $item) {
            $menuItem = MenuItem::find($item['menu_item_id']);
            for ($i = 0; $i < $item['quantity']; $i++) {
                $prices[] = $menuItem->price;
            }
        }
        
        sort($prices);
        $discount = 0;
        for ($i = 0; $i < floor(count($prices) / 2); $i++) {
            $discount += $prices[$i];
        }
        
        return $discount;
    }

    protected function hasFreeSubscription(Shop $shop): bool
    {
        return $shop->subscription && $shop->subscription->payment_plan === 'free';
    }

    protected function deductCommission($ownerId, $amount, $order)
    {
        try {
            $owner = User::with('wallet')->find($ownerId);
            
            if (!$owner->wallet) {
                $owner->wallet()->create([
                    'balance' => 0,
                    'currency' => 'NGN'
                ]);
            }

            if ($owner->wallet->balance < $amount) {
                $owner->wallet->transactions()->create([
                    'amount' => $amount,
                    'type' => 'debit',
                    'status' => 'failed',
                    'description' => 'Commission for order #' . $order->order_number,
                    'reference' => 'COMM-' . Str::random(10),
                    'meta' => [
                        'order_id' => $order->id,
                        'reason' => 'Insufficient balance'
                    ]
                ]);
                throw new \Exception("Insufficient wallet balance");
            }

            $owner->wallet->decrement('balance', $amount);
            
            $owner->wallet->transactions()->create([
                'amount' => $amount,
                'type' => 'debit',
                'status' => 'completed',
                'description' => 'Commission for order #' . $order->order_number,
                'reference' => 'COMM-' . Str::random(10),
                'meta' => [
                    'order_id' => $order->id,
                    'order_total' => $order->order_total,
                    'net_amount' => $order->net_amount
                ]
            ]);

            $order->update(['commission_processed' => true]);

        } catch (\Exception $e) {
            \Log::error("Commission deduction failed: " . $e->getMessage());
            throw $e; // Re-throw to handle in calling method
        }
    }


    public function getAllOrders()
    {
        $user = auth()->user();
        $orders = Order::where('shop_id', $user->shop_id)->with(['items.menuItem' => function ($query) {
            $query->select('id', 'name', 'image_path', 'price');
        }])->get();
    
        $orders->map(function ($order) {
            $order->items->map(function ($item) {
                $item->product_name = $item->menuItem->name;
                $item->product_image = $item->menuItem->image_url;
                $item->product_amount = $item->menuItem->price;
                $item->quantity = $item->quantity;
                unset($item->menuItem); 
            });
            return $order;
        });
    
        return response()->json($orders);
    }

    public function getUserOrders($userId)
    {
        $user = auth()->user();

        $orders = Order::where('user_id', $user->id)
        ->with([
            'items.menuItem' => function ($query) {
                $query->select('id', 'name', 'image_path', 'price');
            },
            'shop' => function ($query) {
                $query->select('id', 'shop_name', 'address'); 
            }
        ])
        ->get();
    
        $orders->map(function ($order) {
            $order->items->map(function ($item) {
                $item->product_name = $item->menuItem->name;
                $item->product_image = $item->menuItem->image_url;
                $item->product_amount = $item->menuItem->price;
                $item->quantity = $item->quantity;
                unset($item->menuItem); 
            });
            return $order;
        });
    
        return response()->json($orders);
    }

    public function getOrderByTrackingId($trackingId)
    {
        $order = Order::where('tracking_number', $trackingId)
            ->with([
                'items.menuItem' => function ($query) {
                    $query->select('id', 'name', 'image_path', 'price');
                },
                'rating',
                'shop' => function ($query) {
                    $query->select('id', 'shop_name', 'address');
                }
            ])
            ->first(); 

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $order->items->map(function ($item) {
            $item->product_name = $item->menuItem->name;
            $item->product_image = $item->menuItem->image_url;
            $item->product_amount = $item->menuItem->price;
            $item->quantity = $item->quantity;
            unset($item->menuItem); 
            return $item;
        });

        return response()->json($order);
    }

    public function getOrderById($orderId)
    {
        $order = Order::where('id', $orderId)
            ->with([
                'items.menuItem' => function ($query) {
                    $query->select('id', 'name', 'image_path', 'price');
                },
                'rating',
                'shop' => function ($query) {
                    $query->select('id', 'shop_name', 'address');
                }
            ])
            ->first(); 

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $order->items->map(function ($item) {
            $item->product_name = $item->menuItem->name;
            $item->product_image = $item->menuItem->image_url;
            $item->product_amount = $item->menuItem->price;
            $item->quantity = $item->quantity;
            unset($item->menuItem); 
            return $item;
        });

        return response()->json($order);
    }

    

    public function getHomeOrders() {
        $user = auth()->user();
        $orders = Order::where('shop_id', $user->shop_id)->where('order_type', 'delivery')->with(['items.menuItem' => function ($query) {
            $query->select('id', 'name', 'image_path', 'price');
        }])->get();
    
        $orders->map(function ($order) {
            $order->items->map(function ($item) {
                $item->product_name = $item->menuItem->name;
                $item->product_image = $item->menuItem->image_url;
                $item->product_amount = $item->menuItem->price;
                $item->quantity = $item->quantity;
                unset($item->menuItem); 
            });
            return $order;
        });
        return response()->json($orders);
    }


    public function getTableOrders() {
        $user = auth()->user();
        $orders = Order::where('shop_id', $user->shop_id)->where('order_type', 'table')->with(['items.menuItem' => function ($query) {
            $query->select('id', 'name', 'image_path', 'price');
        }])->get();
    
        $orders->map(function ($order) {
            $order->items->map(function ($item) {
                $item->product_name = $item->menuItem->name;
                $item->product_image = $item->menuItem->image_url;
                $item->product_amount = $item->menuItem->price;
                $item->quantity = $item->quantity;
                unset($item->menuItem); 
            });
            return $order;
        });
        return response()->json($orders);
    }

    // getPendingOrders
    public function getPendingOrders() {
        $user = auth()->user();
        $orders = Order::where('shop_id', $user->shop_id)->where('order_status', 'pending')->with(['items.menuItem' => function ($query) {
            $query->select('id', 'name', 'image_path', 'price');
        }])->get();
    
        $orders->map(function ($order) {
            $order->items->map(function ($item) {
                $item->product_name = $item->menuItem->name;
                $item->product_image = $item->menuItem->image_url;
                $item->product_amount = $item->menuItem->price;
                $item->quantity = $item->quantity;
                unset($item->menuItem); 
            });
            return $order;
        });
        return response()->json($orders);
    }

    public function updateOrderStatus($id, $status) {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }
        $order->order_status = $status;
        $order->save();
        return response()->json(['message' => 'Order status updated successfully.']);
    }


    public function submitOrder(Request $request)
    {
        
        $validator = $request->validate([
            'design' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'message' => 'nullable|string|max:255',
        ]);

        $order = QRCodeOrder::create($validator);
        
        $notificationController = new EmailController();
        $notificationController->sendQROrderNotification($request->email, $order);
        
        return response()->json(['message' => 'Order submitted successfully!', 'order' => $order], 201);
    }
    
}
