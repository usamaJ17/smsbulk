<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BulkSms extends Model
{
    use HasFactory;
    protected $fillable = [
        'message',
        'phone',
        'sender_id',
        'status',
        's_key',
        'user_name',
        'click_send_id'
    ];
    protected $table = 'bulk_sms';
}
