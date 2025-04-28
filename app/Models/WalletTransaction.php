<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'amount',
        'type',
        'status',
        'description',
        'reference',
        'meta'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}