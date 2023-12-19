<?php

namespace App\Jobs;

use App\Library\Traits\Trackable;
use App\Models\Blacklists;
use App\Models\ContactGroups;
use App\Models\Contacts;
use Carbon\Carbon;
use Exception;
use libphonenumber\PhoneNumberUtil;

/**
 * @method batch()
 */
class ImportContacts extends Base
{

    use Trackable;

    public int      $timeout = 7200;
    protected int   $customer_id;
    protected int   $group_id;
    protected       $list;
    protected int   $total;
    protected array $db_fields;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param $customer_id
     * @param $group_id
     * @param $list
     * @param $db_fields
     * @param $total
     */
    public function __construct($customer_id, $group_id, $list, $db_fields, $total)
    {
        $this->list        = $list;
        $this->customer_id = $customer_id;
        $this->group_id    = $group_id;
        $this->db_fields   = $db_fields;
        $this->total       = $total;

        $this->afterDispatched(function ($thisJob, $monitor) {
            $monitor->setJsonData([
                    'percentage' => 0,
                    'total'      => 0,
                    'processed'  => 0,
                    'failed'     => 0,
                    'message'    => __('locale.contacts.import_being_queued_for_processing'),
                    'logfile'    => null,
            ]);
        });
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $phone_numbers = Contacts::where('group_id', $this->group_id)->where('customer_id', $this->customer_id)->pluck('phone')->toArray();
        $blacklists    = Blacklists::where('user_id', $this->customer_id)->pluck('number')->toArray();

        $processed = 0;
        $failed    = 0;
        $total     = $this->total;

        $list = [];
        foreach ($this->list as $line) {
            $get_data = array_combine($this->db_fields, $line);
            unset($get_data['--']);
            $get_data['uid']         = uniqid();
            $get_data['customer_id'] = $this->customer_id;
            $get_data['group_id']    = $this->group_id;
            $get_data['status']      = 'subscribe';
            $get_data['created_at']  = now()->toDateTimeString();
            $get_data['updated_at']  = now()->toDateTimeString();

            if (isset($get_data['phone'])) {

                $phone = str_replace(['(', ')', '+', '-', ' '], '', $get_data['phone']);
                if (isset($get_data['birth_date'])) {
                    if (Carbon::hasFormat($get_data['birth_date'], 'd/m/y')) {
                        $get_data['birth_date'] = Carbon::createFromFormat('d/m/y', $get_data['birth_date'])->format('Y-m-d');
                    } elseif (Carbon::hasFormat($get_data['birth_date'], 'd/m/Y')) {
                        $get_data['birth_date'] = Carbon::createFromFormat('d/m/Y', $get_data['birth_date'])->format('Y-m-d');
                    } elseif (Carbon::hasFormat($get_data['birth_date'], 'Y-m-d')) {
                        $get_data['birth_date'] = Carbon::createFromFormat('Y-m-d', $get_data['birth_date'])->format('Y-m-d');
                    } else {
                        $get_data['birth_date'] = null;
                    }

                }
                if (isset($get_data['anniversary_date'])) {
                    if (Carbon::hasFormat($get_data['anniversary_date'], 'd/m/y')) {
                        $get_data['anniversary_date'] = Carbon::createFromFormat('d/m/y', $get_data['anniversary_date'])->format('Y-m-d');
                    } elseif (Carbon::hasFormat($get_data['anniversary_date'], 'd/m/Y')) {
                        $get_data['anniversary_date'] = Carbon::createFromFormat('d/m/Y', $get_data['anniversary_date'])->format('Y-m-d');
                    } elseif (Carbon::hasFormat($get_data['anniversary_date'], 'Y-m-d')) {
                        $get_data['anniversary_date'] = Carbon::createFromFormat('Y-m-d', $get_data['anniversary_date'])->format('Y-m-d');
                    } else {
                        $get_data['anniversary_date'] = null;
                    }
                }

                try {
                    $phoneUtil         = PhoneNumberUtil::getInstance();
                    $phoneNumberObject = $phoneUtil->parse('+'.$phone);
                    if ($phoneUtil->isPossibleNumber($phoneNumberObject)) {

                        if ( ! in_array($phone, $phone_numbers) && ! in_array($phone, $blacklists)) {
                            $get_data['phone'] = $phone;
                            $list[]            = $get_data;
                            $processed++;
                        }
                    } else {
                        $failed++;
                    }
                } catch (Exception) {
                    $failed++;
                    continue;
                }
            }
        }

        $percentage = ($total && $processed) ? (int) ($processed * 100 / $total) : 0;

        $this->monitor->updateJsonData([
                'percentage' => $percentage,
                'total'      => $total,
                'processed'  => $processed,
                'failed'     => $failed,
                'message'    => sprintf('Processed: %s/%s, Skipped: %s', $processed, $total, $failed),
        ]);

        if ( ! empty($list)) {
            Contacts::insert($list);
        }

        $check_group = ContactGroups::find($this->group_id);
        $check_group?->updateCache('SubscribersCount');
    }

}
