<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactReply extends Model
{
    protected $fillable = ['request_id', 'body', 'status', 'sent_at'];

    protected $hidden = ['request_id'];

    protected $casts = ['sent_at' => 'datetime'];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'contact_message_id');
    }
}
