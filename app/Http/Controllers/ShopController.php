<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\User;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Wallet;
use App\Models\MenuView;
use App\Models\Subscription;
use Illuminate\Support\Facades\File; 
use Illuminate\Support\Facades\RateLimiter;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;



class ShopController extends Controller
{
    // public function index() {
    //     $shops = Shop::select('id', 'shop_name', 'slug', 'banner', 'opening_time', 'closing_time', 'category', 'rating', 'phone_number', 'address', 'city', 'state', 'country', 'description', 'services_rendered', 'account_number', 'account_name', 'account_bank', 'status')
    //         ->withCount('ratings') 
    //         ->withAvg('ratings', 'rating') 
    //         ->where('status', 'active')
    //         ->get();
    
    //     return response()->json($shops);
    // }

    public function index()
    {
        $shops = Shop::query()
            ->select([
                'id', 'shop_name', 'slug', 'banner', 'opening_time', 'closing_time', 
                'category', 'rating', 'phone_number', 'address', 'city', 'state', 
                'country', 'description', 'services_rendered', 'account_number', 
                'account_name', 'account_bank', 'status'
            ])
            ->withCount('ratings')
            ->withAvg('ratings', 'rating')
            ->where('status', 'active')
            ->orderBy('shop_name')
            ->cursorPaginate(100); 
        
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

    public function registerShopWithOnboarding(Request $request)
    {
        // Validate the request structure
        $request->validate([
            'basicInfo' => 'required|array',
            'basicInfo.name' => 'required|string|max:255',
            'basicInfo.email' => 'required|string|email|max:255|unique:users,email',
            'basicInfo.password' => 'required|string|min:8',
            'basicInfo.businessName' => 'required|string|max:255',
            'basicInfo.businessType' => 'required|string|max:255',
            'basicInfo.phone' => 'required|string|max:20',
            'basicInfo.address' => 'string',
            'basicInfo.city' => 'string',
            'basicInfo.state' => 'string',
            'basicInfo.termsAccepted' => 'required|boolean',
            'hours' => 'required|array',
            'hours.opening_time' => 'nullable|string',
            'hours.closing_time' => 'nullable|string',
            'products' => 'nullable|array',
            'products.*.name' => 'nullable|string',
            'products.*.price' => 'nullable|numeric',
            'products.*.category' => 'nullable|string',
            'payment' => 'nullable|array',
            'payment.bankDetails' => 'nullable|array',
            'payment.bankDetails.accountNumber' => 'nullable|string',
            'payment.bankDetails.accountName' => 'nullable|string',
            'payment.bankDetails.bankName' => 'nullable|string',
            'payment.bankDetails.bankCode' => 'nullable|string',
            'branding' => 'nullable|array',
        ]);

        // dd($request->all());

        // Check if user exists first
        if (User::where('email', $request->basicInfo['email'])->exists()) {
            return response()->json(['message' => 'User already exists', 'status' => 'error'], 409);
        }

        // Start database transaction
        DB::beginTransaction();

        try {
            $bannerPath = $this->processBase64Image($request->branding['banner'] ?? null, 'banners', $request->basicInfo['businessName']);

            // Create Shop
            $shopData = [
                'shop_name' => $request->basicInfo['businessName'],
                'category' => $request->basicInfo['businessType'],
                'address' => $request->basicInfo['address'],
                'city' => $request->basicInfo['city'] ?? null,
                'state' => $request->basicInfo['state'] ?? null,
                'country' => 'Nigeria',
                'account_number' => $request->payment['bankDetails']['accountNumber'],
                'account_name' => $request->payment['bankDetails']['accountName'],
                'account_bank' => $request->payment['bankDetails']['bankName'],
                'account_bank_code' => $request->payment['bankDetails']['bankCode'],
                'phone_number' => $request->basicInfo['phone'],
                'email' => $request->basicInfo['email'],
                'opening_time' => $request->hours['opening_time'],
                'closing_time' => $request->hours['closing_time'],
                'status' => 'active',
                'banner' => $bannerPath ?? null,

                'logo' => $request->branding['logo'] ?? null,
                'description' => $request->branding['description'] ?? null,
            ];

            $shop = Shop::create($shopData);

            // Create User
            $user = User::create([
                'name' => $request->basicInfo['name'],
                'email' => $request->basicInfo['email'],
                'phone_number' => $request->basicInfo['phone'],
                'shop_id' => $shop->id,
                'password' => Hash::make($request->basicInfo['password']),
                'role' => 'admin',
                'email_verification_token' => Str::random(60),
            ]);

            // Update shop with admin ID
            $shop->update(['admin_id' => $user->id]);

            $categoryMap = [];
            foreach ($request->products as $product) {
                // Find or create category
                if (!isset($categoryMap[$product['category']])) {
                    $category = Category::firstOrCreate(
                        ['name' => $product['category']],
                        ['shop_id' => $shop->id]
                    );
                    $categoryMap[$product['category']] = $category->id;
                }

                // Create menu item
                MenuItem::create([
                    'shop_id' => $shop->id,
                    'category_id' => $categoryMap[$product['category']],
                    'name' => $product['name'],
                    'description' => $product['description'] ?? null,
                    'price' => $product['price'],
                    'image_path' => $product['image'] ?? 'https://api.orderrave.ng/public/images/menu_items/placeholder.jpg',
                    'processing_time' => '15-30 mins', // Default value
                    'status' => 'inactive' // inactive if there is no image,
                ]);
            }

            // Create default subscription
            $subscription = new SubscriptionController();
            $subscription->createFreePlan($shop->id, $user->id);

            // Commit transaction
            DB::commit();

            return response()->json([
                'message' => 'Shop registration successful',
                'shop' => $shop,
                'user' => $user,
                'menu_items_count' => count($request->products),
                'status' => 'success',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Shop registration failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Shop registration failed',
                'error' => $e->getMessage(),
                'status' => 'error',
            ], 204); 
        }
    }

    /**
     * Process base64 encoded image and store it
     */
    private function processBase64Image($base64String, $folder, $shopName = null)
    {
        if (!$base64String) {
            return null;
        }

        try {
            // Extract the image data and extension from the base64 string
            if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
                $imageType = $matches[1];
                $imageData = substr($base64String, strpos($base64String, ',') + 1);
                $imageData = base64_decode($imageData);
                
                if ($imageData === false) {
                    throw new \Exception('Invalid base64 image data');
                }
            } else {
                // If no prefix, assume it's raw base64
                $imageType = 'png';
                $imageData = base64_decode($base64String);
                
                if ($imageData === false) {
                    throw new \Exception('Invalid base64 image data');
                }
            }

            // Validate image type
            $validTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array(strtolower($imageType), $validTypes)) {
                throw new \Exception('Invalid image type');
            }

            // Generate unique filename
            $filename = Str::random(20) . '.' . $imageType . ($shopName ? '-' . $shopName : '');
            // $storagePath = "public/{$folder}/{$filename}";
            $fullPath = public_path("images/{$folder}/{$filename}");
            $imagePath =  "images/{$folder}/{$filename}";

            // Ensure directory exists
            if (!File::exists(dirname($fullPath))) {
                File::makeDirectory(dirname($fullPath), 0755, true);
            }

            // Save the original image
            file_put_contents($fullPath, $imageData);

            // Resize if needed (example: resize banner to 1200x300)
            if ($folder === 'banners') {
                $this->resizeImageBanner($fullPath, $fullPath);
            } elseif ($folder === 'logos') {
                $this->resizeImageBanner($fullPath, $fullPath);
            }

            return $imagePath; // Return the path to the stored image

        } catch (\Exception $e) {
            \Log::error('Image processing error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process product image (base64 or URL)
     */
    private function processProductImage($image)
    {
        if (!$image) {
            return null;
        }

        // If it's a base64 image
        if (strpos($image, 'data:image') === 0 || (base64_decode($image, true) !== false)) {
            return $this->processBase64Image($image, 'products');
        }

        // If it's a URL, return as is (or download and process if needed)
        return $image;
    }

    /**
     * Resize an image file
     */
    private function resizeImageBanner($imagePath, $targetPath) {
        list($width, $height, $type) = getimagesize($imagePath);
        $newWidth = 500;
        $newHeight = ($height / $width) * $newWidth;

        $imageResized = imagecreatetruecolor($newWidth, $newHeight);

        // Detect image type from file
        $source = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($imagePath);
                break;
        }

        if ($source) {
            imagecopyresampled($imageResized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            $compressionQuality = 75;
            if ($type == IMAGETYPE_JPEG) {
                imagejpeg($imageResized, $targetPath, $compressionQuality);
            } elseif ($type == IMAGETYPE_PNG) {
                imagepng($imageResized, $targetPath);
            } elseif ($type == IMAGETYPE_GIF) {
                imagegif($imageResized, $targetPath);
            }

            imagedestroy($imageResized);
            imagedestroy($source);
        }
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
            'banner_url' => 'nullable|string|url', 
            'services_rendered' => 'nullable|array',
            'account_number' => 'nullable|string',
            'account_name' => 'nullable|string',
            'account_bank' => 'nullable|string',
            'account_bank_code' => 'nullable|string',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'card_background' => 'nullable|string',
        ]);

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
            unset($validated['banner_url']); 
        } elseif ($request->has('banner_url')) {
            $validated['banner'] = $request->banner_url; 
        } else {
            unset($validated['banner']); 
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

    public function updateStyling(Request $request)
    {
        try {

          $validated = $request->validate([
            'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'card_background' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $shop = Shop::where('id', $request->shop_id)->first();

        if (!$shop) {
            return response()->json(['message' => 'Shop not found'], 404);
        }

        $shop->update([
            'primary_color' => $request->primary_color,
            'secondary_color' => $request->secondary_color,
            'card_background' => $request->card_background,
        ]);

        return response()->json([
            'message' => 'Styling updated successfully',
            'data' => $shop
        ], 200);

     } catch (\Throwable $th) {
            return response()->json(['message' => 'An error occurred while updating the styling', 'error' => $th->getMessage()], 500);
        }
    }
}
