<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignUser extends Model
{
    use HasFactory;
    protected $table = 'campaign_user';
    protected $fillable = [
        'campaign_id',
        'user_id',
        'count',
    ];
}
