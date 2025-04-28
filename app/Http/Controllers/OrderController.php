<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Shop;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\QRCodeOrder;
use App\Models\Subscription;
use Illuminate\Support\Str;


class OrderController extends Controller
{


    // public function createOrder(Request $request)
    // {
    //     // Fetch the shop details
    //     $shop = Shop::where('id', $request->shopId)->first();
    //     if (!$shop) {
    //         return response()->json(['message' => 'Shop not found.', 'status' => 400], 400);
    //     }
        
    //     // Fetch user details if available
    //     $user = $request->user_id ? User::where('id', $request->user_id)->first() : null;

    //     // Validate request data
    //     $validated = $request->validate([
    //         'items' => 'required|array',
    //         'items.*.menu_item_id' => 'required|exists:menu_items,id',
    //         'items.*.quantity' => 'required|integer|min:1',
    //         'type' => 'required|string|in:table,delivery,room',
    //         'name' => 'required|string',
    //         'shopId' => 'required|exists:shops,id',
    //         'tableNumber' => 'nullable|string',
    //         'hotelRoom' => 'nullable|string',
    //         'note' => 'nullable|string',
    //         'address' => 'nullable|string',
    //         'phone' => 'nullable|string',
    //         'paymentStatus' => 'nullable|string',
    //         'email' => 'nullable|email', // Make email optional
    //     ]);

    //     // Check if login is required for delivery orders
    //     if ($validated['type'] === 'delivery' && !$request->user_id) {
    //         return response()->json(['message' => 'Login required for delivery orders, please login to continue.', 'status' => 400], 400);
    //     }

    //     // Calculate order total
    //     $orderTotal = 0;
    //     $itemDetails = ''; 
    //     foreach ($validated['items'] as $item) {
    //         $menuItem = \App\Models\MenuItem::find($item['menu_item_id']);
    //         $orderTotal += $menuItem->price * $item['quantity'];
    //         $itemDetails .= "- {$menuItem->name} (Quantity: {$item['quantity']}, Price: ₦{$menuItem->price})\n";
    //     }

    //     // Generate a unique tracking ID
    //     $trackingId = Str::random(12);

    //     // Create the order
    //     $order = Order::create([
    //         'user_id' => $user ? $user->id : null,
    //         'shop_id' => $validated['shopId'],
    //         'order_number' => strtoupper(uniqid('ORD-')),
    //         'order_status' => 'pending',
    //         'order_type' => $validated['type'],
    //         'order_total' => $orderTotal,
    //         'additional_notes' => $validated['note'],
    //         'address' => $validated['address'],
    //         'user_phone' => $validated['phone'],
    //         'user_name' => $validated['name'],
    //         'table_number' => $validated['tableNumber'],
    //         'hotel_room' => $validated['hotelRoom'],
    //         'tracking_number' => $trackingId,
    //         'payment_status' => $request->paymentStatus ?? '',
    //     ]);

    //     // Add items to the order
    //     foreach ($validated['items'] as $item) {
    //         $order->items()->create([
    //             'menu_item_id' => $item['menu_item_id'],
    //             'quantity' => $item['quantity'],
    //         ]);
    //     }

    //     // Prepare the response
    //     return response()->json([
    //         'message' => 'Order created successfully.',
    //         'order' => [
    //             'orderDetails' => $order,
    //             'items' => $itemDetails, 
    //             'shop_name' => $shop->shop_name, 
    //         ],
    //         'trackingId' => $trackingId,
    //     ], 201);
    // }


    public function createOrder(Request $request)
    {
        // Fetch the shop details with user and wallet
        $shop = Shop::with(['admin.wallet', 'subscription'])
                    ->find($request->shopId);
                    
        if (!$shop) {
            return response()->json(['message' => 'Shop not found.', 'status' => 400], 400);
        }

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
        ]);

        // Check delivery order login requirement
        if ($validated['type'] === 'delivery' && !$request->user_id) {
            return response()->json(['message' => 'Login required for delivery orders.', 'status' => 400], 400);
        }

        // Calculate order total
        $orderTotal = 0;
        $itemDetails = '';
        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::find($item['menu_item_id']);
            $orderTotal += $menuItem->price * $item['quantity'];
            $itemDetails .= "- {$menuItem->name} (Quantity: {$item['quantity']}, Price: ₦{$menuItem->price})\n";
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
            'order_total' => $orderTotal,
            'commission' => $this->hasFreeSubscription($shop) ? $orderTotal * 0.05 : 0,
            'net_amount' => $this->hasFreeSubscription($shop) ? $orderTotal * 0.95 : $orderTotal,
            'additional_notes' => $validated['note'],
            'address' => $validated['address'],
            'user_phone' => $validated['phone'],
            'user_name' => $validated['name'],
            'table_number' => $validated['tableNumber'],
            'hotel_room' => $validated['hotelRoom'],
            'tracking_number' => $trackingId,
            'commission_processed' => !$this->hasFreeSubscription($shop),
            'payment_status' => $request->paymentStatus ?? '',
        ]);

        // Add order items
        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'menu_item_id' => $item['menu_item_id'],
                'quantity' => $item['quantity'],
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
        ], 201);
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
