<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\Wallet;
use App\Models\MenuView;
use App\Models\Subscription;
use Illuminate\Support\Facades\File; 
use Illuminate\Support\Facades\RateLimiter;
use Jenssegers\Agent\Agent;


class ShopController extends Controller
{
    public function index() {
        $shops = Shop::select('id', 'shop_name', 'slug', 'banner', 'opening_time', 'closing_time', 'category', 'rating', 'phone_number', 'address', 'city', 'state', 'country', 'description', 'services_rendered', 'account_number', 'account_name', 'account_bank', 'status')
            ->withCount('ratings') 
            ->withAvg('ratings', 'rating') 
            ->where('status', 'active')
            ->get();
    
        return response()->json($shops);
    }

    public function markets() {
        $markets = Shop::where('category', 'Market')
            ->with(['menuItems' => function($query) {
                $query->select('id', 'shop_id', 'name', 'image_path', 'price', 'description');
            }])
            ->get();
        
        return response()->json($markets);
    }

    // allStores
    public function allStores() {
        $shops = Shop::all();
        $shops = $shops->map(function ($shop) {
            $subscription = Subscription::where('shop_id', $shop->id)->first();
            $shop->plan = $subscription ? $subscription : null; 
            return $shop;
        });
        return response()->json($shops);
    }

   public function trackMenuView(Request $request, Shop $store)
    {
        $throttleKey = 'menu_view:'.($request->user_id ?: $request->ip());
        
        // Rate limiting: 10 views per minute per user/IP
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json(['message' => 'Too many requests'], 429);
        }
        
        RateLimiter::hit($throttleKey, 60);
        
        // Check for existing view from this IP in the last 1 hour
        $ipExists = MenuView::where('shop_id', $store->id)
            ->where('ip_address', $request->ip())
            ->where('created_at', '>', now()->subHour())
            ->exists();
        
        // Check for unique view from session (if available)
        $sessionExists = false;
        if ($request->hasSession()) {
            $sessionExists = MenuView::where('shop_id', $store->id)
                ->where('session_id', $request->session()->getId())
                ->where('created_at', '>', now()->subHour())
                ->exists();
        }
        
        // Only record if neither IP nor session has viewed in last 1 hour
        if (!$ipExists && !$sessionExists) {
            $view = new MenuView([
                'shop_id' => $store->id,
                'user_id' => $request->user_id,
                'ip_address' => $request->ip(),
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'user_agent' => $request->userAgent(),
            ]);
            
            $store->menuViews()->save($view);
            
            $isUnique = true;
        } else {
            $isUnique = false;
        }
        
        return response()->json([
            'message' => 'Menu view tracked successfully',
            'total_views' => $store->menuViews()->count(),
            'unique_views' => $this->getUniqueViewsCount($store),
            'is_unique' => $isUnique,
            'already_viewed' => $ipExists || $sessionExists,
        ]);
    }

    
    protected function getUniqueViewsCount(Shop $store)
    {
        return $store->menuViews()
            ->select('session_id')
            ->groupBy('session_id')
            ->get()
            ->count();
    }
    
    public function show($slug) {
        $shop = Shop::where('slug', $slug)
            ->with(['menuItems' => function($query) {
                $query->where('status', 'active');
            }, 'menuItems.category'])
            ->first();
    
        if (!$shop) {
            return response()->json(['message' => 'Shop not found', 'status' => 404], 201);
        }

        $subscription = Subscription::where('shop_id', $shop->id)->first();
        $shop->plan = $subscription;

        if ($subscription && $subscription->payment_plan === 'free') {
            $shop->wallet = Wallet::where('user_id', $shop->admin_id)->first();
        } else {
            $shop->wallet = null; 
        }
    
        $shop->menuItems->map(function ($menuItem) {
            $menuItem->category_name = $menuItem->category->name ?? null;
            unset($menuItem->category);
            return $menuItem;
        });
    
        return response()->json($shop);
    }

    // get shop
    public function getShop() {
        $user = auth()->user();
        $shop = Shop::where('id', $user->shop_id)->first();
        return response()->json($shop);
    }

    // update shop 
    public function update(Request $request, $id) {

        if ($request->has('services_rendered') && is_string($request->services_rendered)) {
            $decodedServices = json_decode($request->services_rendered, true);
    
            if (!is_array($decodedServices)) {
                return response()->json(['message' => 'The services rendered must be a valid JSON array.'], 422);
            }
    
            $request->merge(['services_rendered' => $decodedServices]);
        }

        $validated = $request->validate([
            'shop_name' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'phone_number' => 'required|string',
            'description' => 'nullable|string',
            'opening_time' => 'nullable|string',
            'closing_time' => 'nullable|string',
            'category' => 'nullable|string',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,PNG|max:5048',
            'services_rendered' => 'nullable|array',
            'account_number' => 'nullable|string',
            'account_name' => 'nullable|string',
            'account_bank' => 'nullable|string',

        ]);
        
        if ($request->has('services_rendered') && is_string($request->services_rendered)) {
            $validatedData['services_rendered'] = json_decode($request->services_rendered, true);
        }
        
        $shop = Shop::where('id', $id)->first();

        if (!$shop) {
            return response()->json(['message' => 'Shop not found'], 404);
        }


        if ($request->hasFile('banner')) {
            $image = $request->file('banner');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imageDirectory = public_path('images/banners/');
            $imagePath = $imageDirectory . $imageName;

            // Ensure the directory exists
            if (!File::exists($imageDirectory)) {
                File::makeDirectory($imageDirectory, 0755, true);
            }

            $this->resizeImage($image, $imagePath);

            $validated['banner'] = 'public/images/banners/' . $imageName;
        }
        

        $shop->update($validated);

        return response()->json($shop);
    }

    private function resizeImage($image, $path) {
        list($width, $height) = getimagesize($image);
        $newWidth = 500;
        $newHeight = ($height / $width) * $newWidth;

        $imageResized = imagecreatetruecolor($newWidth, $newHeight);

        $source = null;
        if ($image->getClientOriginalExtension() == 'jpeg' || $image->getClientOriginalExtension() == 'jpg') {
            $source = imagecreatefromjpeg($image);
        } elseif ($image->getClientOriginalExtension() == 'png') {
            $source = imagecreatefrompng($image);
        }

        if ($source) {
            imagecopyresampled($imageResized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            $compressionQuality = 75;
            if ($image->getClientOriginalExtension() == 'jpeg' || $image->getClientOriginalExtension() == 'jpg') {
                imagejpeg($imageResized, $path, $compressionQuality);
            } elseif ($image->getClientOriginalExtension() == 'png') {
                imagepng($imageResized, $path);
            }

            imagedestroy($imageResized);
            imagedestroy($source);
        }
    }

    public function updateStatus(Request $request) {
        try {
            $shop = Shop::find($request->id);
    
            if (!$shop) {
                return response()->json(['message' => 'Shop not found'], 404);
            }
    
            $shop->status = $request->status;
            $shop->save();
    
            return response()->json($shop);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while updating the shop status', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function activateFreeTrial(Request $request) {
        try {
            $shop = Shop::find($request->id);
    
            if (!$shop) {
                return response()->json(['message' => 'Shop not found'], 404);
            }
    
            $shop->status = 'active';
            $shop->free_trial = true;
            $shop->save();
    
            return response()->json($shop);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while activating the free trial', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deactivateFreeTrial(Request $request) {
        try {
            $shop = Shop::find($request->id);
    
            if (!$shop) {
                return response()->json(['message' => 'Shop not found'], 404);
            }
    
            $shop->status = 'inactive';
            $shop->free_trial = false;
            $shop->save();
    
            return response()->json($shop);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while deactivating the free trial', 'error' => $e->getMessage()], 500);
        }
    }


    public function activateFreeSubscription(Request $request) {
        try {
            $shop = Shop::find($request->id);
    
            if (!$shop) {
                return response()->json(['message' => 'Shop not found'], 404);
            }

            $subscription = new SubscriptionController();
            $subscription->createFreePlan($shop->id, $shop->admin_id);

            $shop->status = 'active';
            $shop->free_trial = false;
            $shop->save();
    
            return response()->json($shop);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while activating the free subscription', 'error' => $e->getMessage()], 500);
        }
    }
}
