<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Shop;
use App\Models\MenuItem;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File; // Import File facade




class MenuController extends Controller
{

    public function createMenuItem(Request $request) {
    
        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }
    
        // Check file size
        if ($request->file('image')->getSize() > 5048 * 1024) { // 5MB in bytes
            return response()->json(['error' => 'File size exceeds the allowed limit of 5MB.'], 400);
        }
        
    
        // Validate the request
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'shop_id' => 'required|exists:shops,id',
            'processing_time' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5048', // 5MB
        ]);
    
        try {
            // Handle Image
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . Str::slug($image->getClientOriginalName());
                $imageDirectory = public_path('images/menu_items/');
                $imagePath = $imageDirectory . $imageName;
    
                // Ensure the directory exists
                if (!File::exists($imageDirectory)) {
                    File::makeDirectory($imageDirectory, 0755, true);
                }
    
                // Resize and save the image
                $this->resizeImage($image, $imagePath);
    
                $validated['image_path'] = 'public/images/menu_items/' . $imageName;
            }
    
            // Create the menu item
            $menuItem = MenuItem::create($validated);
    
            return response()->json($menuItem, 201);
    
        } catch (\Exception $e) {
            \Log::error('Error creating menu item: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while creating the menu item.'], 500);
        }
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
    
    
    // update menu item 
    public function updateMenuItem(Request $request, $id) {
        try {
            $menuItem = MenuItem::find($id);
    
            if (!$menuItem) {
                return response()->json(['message' => 'Menu item not found'], 404);
            }
    
            // Log file details for debugging
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                \Log::info('File details:', [
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'error_code' => $file->getError(),
                ]);
            }
    
            // Validate the request data
            $validated = $request->validate([
                'name' => 'required|string',
                'description' => 'nullable|string',
                'price' => 'required|numeric',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5048', // validate image
            ]);
    
            // Handle image upload
            if ($request->hasFile('image')) {
                $oldImagePath = $menuItem->image_path;
                if ($oldImagePath) {
                    $oldImagePath = public_path($oldImagePath);
                    if (File::exists($oldImagePath)) {
                        File::delete($oldImagePath);
                    }
                }
                $image = $request->file('image');
    
                // Get the original file name and extension
                $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME); // Get filename without extension
                $extension = $image->getClientOriginalExtension(); // Get file extension
    
                // Generate a unique file name with the original extension
                $imageName = time() . '_' . Str::slug($originalName) . '.' . $extension;
    
                // Define the image directory and path
                $imageDirectory = public_path('images/menu_items/');
                $imagePath = $imageDirectory . $imageName;
    
                // Ensure the directory exists
                if (!File::exists($imageDirectory)) {
                    File::makeDirectory($imageDirectory, 0755, true);
                }
    
                // Resize and save the image
                $this->resizeImage($image, $imagePath);
    
                // Save the image path in the validated data
                $validated['image_path'] = 'public/images/menu_items/' . $imageName;
            }
    
            // Update the menu item
            $menuItem->update($validated);
    
            return response()->json($menuItem);
    
        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error updating menu item: ' . $e->getMessage(), [
                'exception' => $e,
                'stack_trace' => $e->getTraceAsString(),
            ]);
    
            // Return a JSON response with the error message
            return response()->json([
                'message' => 'An error occurred while updating the menu item.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function listCategories() {
        $categories = Category::orderBy('name', 'asc')->get();
        return response()->json($categories);
    }

    public function listMenu() {
        $user = auth()->user();
        $shop = Shop::where('id', $user->shop_id)->first();
        $menuItems = $shop->menuItems()->get();
        $menuItems->each(function ($menuItem) {
            $category = $menuItem->category;
            $menuItem->category_name = $category ? $category->name : null;
        });

        return response()->json($menuItems);
    }


    public function createCategory(Request $request) {
        $user = auth()->user();
        $validated = $request->validate([
            'name' => 'required|string|unique:categories',
            'description' => 'nullable|string',
        ]);

        $validated['shop_id'] = $user->shop_id;

        $category = Category::create($validated);

        if(!$category) {
            return response()->json(['message' => 'Category not created'], 200);
        }
    
        return response()->json($category, 200);
    }

    // update
    public function updateCategory(Request $request, $id) {
        $category = Category::find($id);
    
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
    
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);
    
        $category->update($validated);
    
        return response()->json($category);
    }

    public function deleteCategory($id) {
        $category = Category::find($id);
    
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
    
        $category->delete();
    
        return response()->json(['message' => 'Category deleted successfully']);
    }

    public function deleteMenu($id) {
        $menuItem = MenuItem::find($id);
    
        if (!$menuItem) {
            return response()->json(['message' => 'Menu item not found'], 404);
        }
    
        $menuItem->delete();
    
        return response()->json(['message' => 'Menu item deleted successfully']);
    }

    public function updateStatus(Request $request) {
        $menuItem = MenuItem::find($request->id);
    
        if (!$menuItem) {
            return response()->json(['message' => 'Menu item not found'], 404);
        }
    
        $menuItem->update(['status' => $request->status]);
    
        return response()->json($menuItem);
    }
}


