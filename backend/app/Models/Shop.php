<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = ['name', 'slug', 'address', 'phone', 'timezone', 'settings'];

    protected $casts = ['settings' => 'array'];
}
