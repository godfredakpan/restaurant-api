<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MenuItem;

class ProductController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $menuItems = MenuItem::with('category')->where('shop_id', $user->shop_id)->get()->map(function ($menuItem) {
            return [
                'id' => $menuItem->id,
                'name' => $menuItem->name,
                'price' => $menuItem->price,
                'category' => $menuItem->category->name ?? 'Uncategorized',
                'image' => $menuItem->image_url,
                'total_sold' => $menuItem->totalSold(),
            ];
        });

        return response()->json($menuItems);
    }
}
