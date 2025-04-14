<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;


class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'shop_id'
    ];

    protected static function boot() {
        parent::boot();
    
        static::creating(function ($category) {
            $slug = Str::slug($category->name);
            $originalSlug = $slug;
            $count = 1;
    
            // Ensure slug uniqueness
            while (Category::where('slug', $slug)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }
    
            $category->slug = $slug;
        });
    }
}
