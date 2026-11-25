<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContactMessage extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
        'status',
        'shop_id',
    ];

    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class);
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(ContactReply::class)->latestOfMany();
    }
}
