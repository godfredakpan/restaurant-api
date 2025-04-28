<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'balance', 'currency'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function deposit($amount)
    {
        $this->balance += $amount;
        $this->save();

        // $this->transactions()->create([
        //     'amount' => $amount,
        //     'type' => 'credit',
        //     'description' => 'Wallet deposit',
        //     'status' => 'completed',
        // ]);

        return $this;
    }

    public function withdraw($amount)
    {
        if ($this->balance < $amount) {
            throw new \Exception('Insufficient balance');
        }

        $this->balance -= $amount;
        $this->save();

        // $this->transactions()->create([
        //     'amount' => $amount,
        //     'type' => 'debit',
        //     'description' => 'Wallet withdrawal',
        //     'status' => 'completed'
        // ]);

        return $this;
    }
}