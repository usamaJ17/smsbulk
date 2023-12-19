<?php

namespace App\Models;

use App\Library\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static where(string $string, string $uid)
 * @method static create(array $array)
 * @method static insert(array $list)
 * @method static whereIn(string $string, $list_id)
 * @method static find(mixed $id)
 * @method limit(int $int)
 * @property mixed name
 */
class Contacts extends Model
{
    use HasUid;

    protected $table = 'contacts';

    protected $fillable = [
            'customer_id',
            'group_id',
            'phone',
            'status',
            'email',
            'username',
            'company',
            'first_name',
            'last_name',
            'birth_date',
            'anniversary_date',
            'address',
            'created_at',
            'updated_at',
    ];

    protected $casts = [
            'phone'            => 'integer',
            'birth_date'       => 'datetime',
            'anniversary_date' => 'datetime',
    ];

    /**
     * display contact group name
     *
     * @return BelongsTo
     */
    public function display_group(): BelongsTo
    {
        return $this->belongsTo(ContactGroups::class, 'id');
    }

    /**
     * get custom field
     *
     * @return HasMany
     */
    public function custom_fields(): HasMany
    {
        return $this->hasMany(ContactsCustomField::class, 'contact_id', 'id')->select('tag', 'value');
    }

    /**
     * return contact name
     *
     * @return string
     */
    public function display_name(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

}
