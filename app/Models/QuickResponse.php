<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickResponse extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'name',
    ];
    protected $table = 'quick_response';
}