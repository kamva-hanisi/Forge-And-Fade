<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2', 'received_on' => 'date:Y-m-d', 'voided_at' => 'datetime'];
}
