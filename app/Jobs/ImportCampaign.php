<?php

namespace App\Jobs;

use App\Library\SMSCounter;
use App\Library\Tool;
use App\Models\Campaigns;
use App\Models\CsvData;
use App\Models\CustomerBasedPricingPlan;
use App\Models\FileCampaignData;
use App\Models\PlansCoverageCountries;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

class ImportCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Campaigns $campaign;
    protected CsvData   $csvData;
    protected           $db_fields;
    protected           $plan_id;

    /**
     * Create a new job instance.
     */
    public function __construct(Campaigns $campaign, CsvData $csvData, $db_fields, $plan_id)
    {
        $this->campaign  = $campaign;
        $this->csvData   = $csvData;
        $this->db_fields = $db_fields;
        $this->plan_id   = $plan_id;
    }

    /**
     * Execute the job.
     *
     * @throws NumberParseException
     */
    public function handle(): void
    {
        $csv_data        = Excel::toArray(new stdClass(), storage_path($this->csvData->csv_data))[0];
        $campaign_fields = $csv_data[0];
        $collection      = collect($csv_data)->skip($this->csvData->csv_header);
        $total           = $collection->count();
        $sending_server  = isset($this->campaign->sending_server_id) ? $this->campaign->sending_server_id : null;

        $this->campaign->cache = json_encode([
                'ContactCount'         => $total,
                'DeliveredCount'       => 0,
                'FailedDeliveredCount' => 0,
                'NotDeliveredCount'    => 0,
        ]);

        $this->campaign->update();

        Tool::resetMaxExecutionTime();

        $importData      = [];
        $check_sender_id = $this->campaign->getSenderIds();

        $collection->chunk(1000)->each(function ($lines) use (&$importData, $check_sender_id, $campaign_fields, &$sending_server) {

            foreach ($lines as $line) {

                $sender_id = count($check_sender_id) > 0 ? $this->campaign->pickSenderIds() : null;
                $message   = null;

                $data = array_combine($this->db_fields, $line);

                if ($data['phone'] != null) {

                    $phone = str_replace(['+', '(', ')', '-', ' '], '', $data['phone']);

                    $sms_type  = $this->campaign->sms_type;
                    $sms_count = 1;

                    if (Tool::validatePhone($phone)) {
                        if ($this->campaign->message) {
                            $b           = array_map('trim', $line);
                            $modify_data = array_combine($campaign_fields, $b);
                            $message     = Tool::renderSMS($this->campaign->message, $modify_data);

                            $sms_counter  = new SMSCounter();
                            $message_data = $sms_counter->count($message);
                            $sms_count    = $message_data->messages;

                            if ($sms_type == 'plain') {
                                if ($message_data->encoding == 'UTF16') {
                                    $sms_type = 'unicode';
                                }
                            }
                        }

                        $phoneUtil         = PhoneNumberUtil::getInstance();
                        $phoneNumberObject = $phoneUtil->parse('+'.$phone);
                        $countryCode       = $phoneNumberObject->getCountryCode();

                        $coverage = CustomerBasedPricingPlan::where('user_id', $this->campaign->user_id)
                                                            ->whereHas('country', function ($query) use ($countryCode) {
                                                                $query->where('country_code', $countryCode)
                                                                      ->where('status', 1);
                                                            })
                                                            ->with('sendingServer')
                                                            ->first();

                        if ( ! $coverage) {
                            $coverage = PlansCoverageCountries::where(function ($query) use ($countryCode) {
                                $query->whereHas('country', function ($query) use ($countryCode) {
                                    $query->where('country_code', $countryCode)
                                          ->where('status', 1);
                                })->where('plan_id', $this->plan_id);
                            })->with('sendingServer')->first();
                        }

                        if ( ! $coverage) {
                            continue;
                        }

                        $priceOption = json_decode($coverage->options, true);
                        if ($sending_server == null) {
                            $sending_server = $coverage->sendingServer->id;
                        }

                        $cost = $this->campaign->getCost($priceOption);

                        $importData[] = [
                                'user_id'           => $this->campaign->user_id,
                                'sending_server_id' => $sending_server,
                                'campaign_id'       => $this->campaign->id,
                                'sender_id'         => $sender_id,
                                'phone'             => $phone,
                                'sms_type'          => $sms_type,
                                'sms_count'         => $sms_count,
                                'cost'              => $cost,
                                'message'           => $message,
                                'created_at'        => Carbon::now(),
                                'updated_at'        => Carbon::now(),
                        ];

                    }

                }
            }

            FileCampaignData::insert($importData);

        });

        $this->campaign->execute();

    }
}
