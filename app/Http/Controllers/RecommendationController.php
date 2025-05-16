<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecommendationController extends Controller
{
    private $defaultImage = '/images/default-food.jpg';
    private $recommendationCount = 5; // Constant for number of recommendations

    public function getTimeBasedRecommendations(Request $request)
    {
        $currentHour = Carbon::now()->hour;
        $mealTime = $this->getMealTime($currentHour);

        // Get recommendations ensuring we always have 5 items
        $recommendations = $this->getRecommendations($mealTime);

        // Format for frontend with fallback image
        $formattedRecs = $recommendations->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'image_url' => $item->image_path ? asset($item->image_path) : asset($this->defaultImage),
                'is_fallback' => $item->is_fallback ?? false
            ];
        });

        return response()->json([
            $mealTime => $formattedRecs,
            'meal_time' => $mealTime
        ]);
    }

    private function getRecommendations($mealTime)
    {
        $defaults = [
            'breakfast' => [
                'Akara and Pap', 
                'Bread and Tea', 
                'Indomie', 
                'Yam and Egg', 
                'Moi Moi', 
                'Cereals', 
                'Agege Bread and Ewa Agoyin', 
                'Moi Moi with Custard', 
                'Fried Yam and Egg Sauce', 
                'Dodo and Fried Eggs', 
                'Ekuru with Pepper Sauce'
            ],
            'lunch' => [
                'Jollof Rice', 
                'Fried Rice', 
                'Afang Soup',
                'Soup',
                'Chicken Salad',
                'Salad',
                'Egusi',
                'Pounded Yam with Egusi Soup', 
                'Efo Riro with Amala', 
                'White Rice with Obe Ata Dindin', 
                'Beans and Fried Plantain'
            ],
            'dinner' => [
                'Eba and Okro', 
                'Rice and Beans', 
                'Salad',
                'Tuwo Shinkafa with Miyan Kuka', 
                'Porridge Yam', 
                'Plantain Porridge', 
                'Nkwobi'
            ]
        ];

        // Shuffle the defaults for randomness
        shuffle($defaults[$mealTime]);

        $recommendations = collect();

        // 1. Try time-based popular items
        $timeBasedItems = $this->getTimeBasedItems($mealTime, $defaults[$mealTime]);
        $recommendations = $recommendations->merge($timeBasedItems)
        ->unique(fn ($item) => strtolower($item->name));

        // If we already have enough, return them
        if ($recommendations->count() >= $this->recommendationCount) {
            return $recommendations->take($this->recommendationCount);
        }

        // 2. Try general popular items
        $popularItems = $this->getPopularItems($defaults[$mealTime]);
        $recommendations = $recommendations->merge($popularItems)
        ->unique(fn ($item) => strtolower($item->name)) 
        ->take($this->recommendationCount);

        // If we now have enough, return them
        if ($recommendations->count() >= $this->recommendationCount) {
            return $recommendations;
        }

        // 3. Fallback to default items from database
        $needed = $this->recommendationCount - $recommendations->count();
        $fallbackItems = MenuItem::where(function ($query) use ($defaults, $mealTime) {
                foreach ($defaults[$mealTime] as $name) {
                    $query->orWhere('name', 'LIKE', "%$name%");
                }
            })
            ->whereNotIn('id', $recommendations->pluck('id')->toArray())
            ->limit($needed)
            ->get()
            ->each(fn($item) => $item->is_fallback = true);

        $recommendations = $recommendations->merge($fallbackItems)
            ->unique('name')
            ->take($this->recommendationCount);

        // If we now have enough, return them
        if ($recommendations->count() >= $this->recommendationCount) {
            return $recommendations;
        }

        // 4. Final fallback - use default names with generated data
        $needed = $this->recommendationCount - $recommendations->count();
        $defaultNames = array_diff(
            array_map('strtolower', $defaults[$mealTime]), 
            array_map('strtolower', $recommendations->pluck('name')->toArray())
        );
        
        $generatedItems = collect(array_slice($defaultNames, 0, $needed))
            ->map(function ($name) {
                return (object)[
                    'id' => 0,
                    'name' => $name,
                    'price' => rand(500, 3000),
                    'image_path' => $this->defaultImage,
                    'is_fallback' => true
                ];
            });

        return $recommendations->merge($generatedItems)->take($this->recommendationCount);
    }

    private function getTimeBasedItems($mealTime, $itemNames)
    {
        $timeRange = $this->getTimeRange($mealTime);
        
        return DB::table('order_items')
            ->select(
                'menu_items.id',
                'menu_items.name',
                'menu_items.price',
                'menu_items.image_path'
            )
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->whereIn('menu_items.name', $itemNames)
            ->whereBetween('orders.created_at', [
                Carbon::today()->setTimeFromTimeString($timeRange[0]),
                Carbon::today()->setTimeFromTimeString($timeRange[1])
            ])
            ->groupBy('menu_items.id', 'menu_items.name', 'menu_items.price', 'menu_items.image_path')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($this->recommendationCount)
            ->get();
    }

    private function getPopularItems($itemNames)
    {
        return DB::table('order_items')
            ->select(
                'menu_items.id',
                'menu_items.name',
                'menu_items.price',
                'menu_items.image_path'
            )
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->whereIn('menu_items.name', $itemNames)
            ->groupBy('menu_items.id', 'menu_items.name', 'menu_items.price', 'menu_items.image_path')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($this->recommendationCount)
            ->get();
    }

   
private function getMealTime($hour)
{
    if ($hour >= 6 && $hour < 11) {
        return 'breakfast';
    } elseif ($hour >= 11 && $hour < 17) { // Adjusted to match the time range in getTimeRange
        return 'lunch';
    } else {
        return 'dinner';
    }
}

private function getTimeRange($mealTime)
{
    return match ($mealTime) {
        'breakfast' => ['06:00:00', '10:59:59'],
        'lunch' => ['11:00:00', '16:59:59'], // Matches the adjusted lunch time
        'dinner' => ['17:00:00', '23:59:59'],
        default => ['00:00:00', '05:59:59'], // Default for late-night or early-morning hours
    };
}
}