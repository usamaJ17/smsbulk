<?php

namespace App\Models;

use App\Jobs\ImportContacts;
use App\Jobs\ReplicateContacts;
use App\Library\Traits\HasCache;
use App\Models\Traits\TrackJobs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static where(string $string, string $uid)
 * @method static offset(mixed $start)
 * @method static whereLike(string[] $array, mixed $search)
 * @method static count()
 * @method static find(mixed $target_group)
 * @method static cursor()
 * @method static whereIn(string $string, array $contact_groups)
 * @method static select(string $string, string $string1)
 * @method create(mixed $list)
 * @property mixed name
 * @property mixed cache
 */
class ContactGroups extends Model
{
    use TrackJobs;
    use HasCache;

    protected $table = 'contact_groups';

    protected $fillable = [
            'customer_id',
            'name',
            'sender_id',
            'send_welcome_sms',
            'unsubscribe_notification',
            'send_keyword_message',
            'status',
            'welcome_sms',
            'unsubscribe_sms',
            'cache',
            'batch_id',
            'sending_server',
    ];


    public static function boot(): void
    {
        parent::boot();

        // Create uid when creating list.
        static::creating(function ($item) {
            // Create new uid
            $uid = uniqid();
            while (self::where('uid', $uid)->count() > 0) {
                $uid = uniqid();
            }
            $item->uid = $uid;
        });

        static::deleted(function ($item) {
            if ( ! is_null($item->contact)) {
                $item->contact->delete();
            }
        });
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
            'status'                   => 'boolean',
            'send_welcome_sms'         => 'boolean',
            'unsubscribe_notification' => 'boolean',
            'send_keyword_message'     => 'boolean',
    ];


    /**
     * get subscribers
     *
     * @return HasMany
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Contacts::class, 'group_id');
    }

    /**
     * get contacts
     *
     * @return BelongsTo
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contacts::class);
    }

    /**
     * @return HasMany
     */
    public function optInKeywords(): HasMany
    {
        return $this->hasMany(ContactGroupsOptinKeywords::class, 'contact_group');
    }

    /**
     * @return HasMany
     */
    public function optOutKeywords(): HasMany
    {
        return $this->hasMany(ContactGroupsOptoutKeywords::class, 'contact_group');
    }

    /**
     * Retrieve contact group cached data.
     *
     * @param      $key
     * @param  null  $default
     *
     * @return mixed|null
     */
    public function readCache($key, $default = null): mixed
    {
        $cache = json_decode($this->cache, true);
        if (is_null($cache)) {
            return $default;
        }
        if (array_key_exists($key, $cache)) {
            if (is_null($cache[$key])) {
                return $default;
            } else {
                return $cache[$key];
            }
        } else {
            return $default;
        }
    }

    /**
     * update cache value
     *
     * @param  null  $key
     */
    public function updateCache($key = null): void
    {
        $index = [
                'SubscribersCount' => function ($group) {
                    return $group->subscribersCount();
                },
        ];

        // retrieve cached data
        $cache = json_decode($this->cache, true);
        if (is_null($cache)) {
            $cache = [];
        }

        if (is_null($key)) {
            // update all cache
            foreach ($index as $key => $callback) {
                $cache[$key] = $callback($this);
                if ($key == 'SubscribersCount') {
                    // SubscriberCount cache must always be updated as its value will be used for the others
                    $this->cache = json_encode($cache);
                    $this->save();
                }
            }
        } else {
            // update specific key
            $callback    = $index[$key];
            $cache[$key] = $callback($this);
        }

        // write back to the DB
        $this->cache = json_encode($cache);
        $this->save();

    }

    /**
     * get total amount of subscribers in single list
     *
     */
    public function subscribersCount($cache = false)
    {
        if ($cache) {
            return $this->readCache('SubscribersCount', 0);
        }

        return $this->subscribers()->count();
    }

    /**
     * get route key by uid
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'uid';
    }


    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }


    /*
    |--------------------------------------------------------------------------
    | Version 3.7
    |--------------------------------------------------------------------------
    |
    |
    |
    */


    public function importJobs()
    {
        return $this->jobMonitors()->orderBy('job_monitors.id', 'DESC')
                    ->whereIn('job_type', [ImportContacts::class, ReplicateContacts::class]);
    }


    // Strategy pattern here
    public function getProgress($job)
    {
        if ($job->hasBatch()) {
            $progress               = $job->getJsonData();
            $progress['status']     = $job->status;
            $progress['error']      = $job->error;
            $progress['percentage'] = $job->getBatch()->progress();
            $progress['total']      = $job->getBatch()->totalJobs;
            $progress['processed']  = $job->getBatch()->processedJobs();
            $progress['failed']     = $job->getBatch()->failedJobs;
        } else {
            $progress           = $job->getJsonData();
            $progress['status'] = $job->status;
            $progress['error']  = $job->error;
            // The following attributes are already available
            // $progress['percentage']
            // $progress['total']
            // $progress['processed']
            // $progress['failed']
        }

        return $progress;
    }

}
