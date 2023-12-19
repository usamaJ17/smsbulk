<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static where(string $string, mixed $id)
 * @method static create(array $array)
 */
class ChatBoxMessage extends Model
{
    use SoftDeletes;
    protected $fillable = [
            'box_id',
            'message',
            'media_url',
            'sms_type',
            'send_by',
            'sending_server_id',
    ];


}
