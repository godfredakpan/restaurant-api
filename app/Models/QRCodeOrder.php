<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QRCodeOrder extends Model
{
    use HasFactory;

    protected $table = 'qr_code_orders';

    protected $fillable = ['design', 'price', 'name', 'email', 'phone', 'message', 'address'];

}
