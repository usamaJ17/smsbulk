<?php

namespace App\Models;

use App\Library\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static where(string $string, string $uid)
 * @method static create(array $array)
 */
class ContactsCustomField extends Model
{
    use HasUid;
    protected $table = 'contacts_custom_field';

    protected $fillable = [
            'contact_id',
            'name',
            'tag',
            'type',
            'required',
            'value',
    ];

}
