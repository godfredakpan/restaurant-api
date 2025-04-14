<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'location', 'ref_code'];

    public function referralHistories()
    {
        return $this->hasMany(ReferralHistory::class, 'referrer_id');
    }
}
