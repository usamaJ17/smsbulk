<?php

namespace App\Models;

use App\Library\Traits\HasUid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property mixed name
 * @method static create(array $array)
 * @method static cursor()
 * @method static where(string $string, string $uid)
 * @method static count()
 * @method static offset($start)
 * @method static whereLike(string[] $array, $search)
 * @method static find(mixed $country_code)
 */
class Country extends Model
{
    use HasUid;

    protected $table = 'countries';

    protected $fillable = ['name', 'iso_code', 'country_code', 'status'];

    /**
     * get iso code using country
     *
     * @param $country
     *
     * @return mixed
     */
    public static function getIsoCode($country): mixed
    {
        return self::where('name', $country)->first()->iso_code;
    }

    /**
     * @var array
     */
    protected $casts = [
            'status' => 'boolean',
    ];

    public function plans_coverage_countries(): HasMany
    {
        return $this->hasMany(PlansCoverageCountries::class, 'country_id', 'id');
    }
}
