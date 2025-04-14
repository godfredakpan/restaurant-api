<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Models\QRCodeOrder;
use App\Models\Subscription;
use Illuminate\Support\Str;


class OrderController extends Controller
{
    // public function createOrder(Request $request)
    // {
    //     $user = $request->user_id ? User::find($request->user_id) : null;

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
    //     ]);

    //     if ($validated['type'] === 'delivery' && !$user) {
    //         return response()->json(['message' => 'Login required for delivery orders, please login to continue.', 'status' => 400], 201);
    //     }

    //     // Calculate order total
    //     $orderTotal = 0;
    //     foreach ($validated['items'] as $item) {
    //         $menuItem = \App\Models\MenuItem::find($item['menu_item_id']);
    //         $orderTotal += $menuItem->price * $item['quantity'];
    //     }

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
    //         'payment_status' => $validated['paymentStatus'] ?? '',
    //     ]);

    //     // Add items to the order
    //     foreach ($validated['items'] as $item) {
    //         $order->items()->create([
    //             'menu_item_id' => $item['menu_item_id'],
    //             'quantity' => $item['quantity'],
    //         ]);
    //     }

    //     return response()->json([
    //         'message' => 'Order created successfully.',
    //         'order' => $order->load('items'),
    //     ], 201);
    // }

    public function createOrder(Request $request)
    {
        // Fetch the shop details
        $shop = Shop::where('id', $request->shopId)->first();
        if (!$shop) {
            return response()->json(['message' => 'Shop not found.', 'status' => 400], 400);
        }
        
        // Fetch user details if available
        $user = $request->user_id ? User::where('id', $request->user_id)->first() : null;

        // Validate request data
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
            'email' => 'nullable|email', // Make email optional
        ]);

        // Check if login is required for delivery orders
        if ($validated['type'] === 'delivery' && !$request->user_id) {
            return response()->json(['message' => 'Login required for delivery orders, please login to continue.', 'status' => 400], 400);
        }

        // Calculate order total
        $orderTotal = 0;
        $itemDetails = ''; 
        foreach ($validated['items'] as $item) {
            $menuItem = \App\Models\MenuItem::find($item['menu_item_id']);
            $orderTotal += $menuItem->price * $item['quantity'];
            $itemDetails .= "- {$menuItem->name} (Quantity: {$item['quantity']}, Price: ₦{$menuItem->price})\n";
        }

        // Generate a unique tracking ID
        $trackingId = Str::random(12);

        // Create the order
        $order = Order::create([
            'user_id' => $user ? $user->id : null,
            'shop_id' => $validated['shopId'],
            'order_number' => strtoupper(uniqid('ORD-')),
            'order_status' => 'pending',
            'order_type' => $validated['type'],
            'order_total' => $orderTotal,
            'additional_notes' => $validated['note'],
            'address' => $validated['address'],
            'user_phone' => $validated['phone'],
            'user_name' => $validated['name'],
            'table_number' => $validated['tableNumber'],
            'hotel_room' => $validated['hotelRoom'],
            'tracking_number' => $trackingId,
            'payment_status' => $request->paymentStatus ?? '',
        ]);

        // Add items to the order
        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'menu_item_id' => $item['menu_item_id'],
                'quantity' => $item['quantity'],
            ]);
        }

        // Prepare the response
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
