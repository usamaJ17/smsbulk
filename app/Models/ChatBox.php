<?php

namespace App\Models;

use App\Library\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static where(string $string, string $uid)
 * @method static create(array $array)
 */
class ChatBox extends Model
{
    use HasUid , SoftDeletes;

    protected $fillable = [
            'user_id',
            'from',
            'to',
            'notification',
            'sending_server_id',
            'reply_by_customer',
    ];

    protected $casts = [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
    ];


    public function boxMessages()
    {
        $this->belongsTo(ChatBoxMessage::class, 'box_id', 'id');
    }

}
