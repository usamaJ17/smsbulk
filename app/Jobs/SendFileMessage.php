<?php

namespace App\Jobs;

use App\Exceptions\CampaignPausedException;
use App\Library\Exception\QuotaExceeded;
use App\Library\QuotaManager;
use DateTime;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendFileMessage implements ShouldQueue
{

    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    protected $sendData;
    protected $campaign;
    protected $triggerId;
    protected $stopOnError = false;

    /**
     * Create a new job instance.
     */
    public function __construct($campaign, $sendData, $triggerId = null)
    {
        $this->campaign  = $campaign;
        $this->sendData  = $sendData;
        $this->triggerId = $triggerId;
    }

    /**
     * @throws Exception
     */
    public function setStopOnError($value): void
    {
        if ( ! is_bool($value)) {
            throw new Exception('Parameter passed to setStopOnError must be bool');
        }

        $this->stopOnError = $value;
    }


    /**
     * Determine the time at which the job should timeout.
     *
     * @return DateTime
     */
    public function retryUntil(): DateTime
    {
        return now()->addHours(12);
    }


    /**
     * @throws QuotaExceeded
     */
    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }
        $this->send();

    }

    /**
     * @throws QuotaExceeded
     * @throws Exception
     */
    public function send($exceptionCallback = null): void
    {
        $subscription = $this->campaign->user->customer->getCurrentSubscription();

        try {

            if ($this->campaign->user->sms_unit != '-1' && $this->campaign->user->sms_unit == 0) {
                throw new CampaignPausedException(sprintf("Campaign `%s` (%s) halted, customer exceeds sms balance", $this->campaign->campaign_name, $this->campaign->uid));
            }

            QuotaManager::with($this->sendData->sendingServer, 'send')->enforce();
            QuotaManager::with($subscription, 'send')->enforce();

            $sms_type = $this->sendData->sms_type;

            $preparedData = [
                    'user_id'        => $this->campaign->user_id,
                    'phone'          => $this->sendData->phone,
                    'sender_id'      => $this->sendData->sender_id,
                    'message'        => $this->sendData->message,
                    'sms_type'       => $sms_type,
                    'cost'           => $this->sendData->cost,
                    'sms_count'      => $this->sendData->sms_count,
                    'campaign_id'    => $this->campaign->id,
                    'sending_server' => $this->sendData->sendingServer,
            ];


            if ($sms_type == 'voice') {
                $preparedData['language'] = $this->campaign->language;
                $preparedData['gender']   = $this->campaign->gender;
            }

            if ($sms_type == 'mms' || $sms_type == 'whatsapp' || $sms_type == 'viber') {
                if (isset($this->campaign->media_url)) {
                    $preparedData['media_url'] = $this->campaign->media_url;
                }

                if (isset($this->campaign->language)) {
                    $preparedData['language'] = $this->campaign->language;
                }
            }

            $getData = $this->campaign->sendSMS($preparedData);
            $this->campaign->updateCache(substr_count($getData->status, 'Delivered') == 1 ? 'DeliveredCount' : 'FailedDeliveredCount');
            $this->sendData->delete();

            if (substr_count($getData->status, 'Delivered') == 1 && $this->campaign->user->sms_unit != '-1') {
                $this->campaign->user->countSMSUnit($getData->cost);
            }

        } catch (QuotaExceeded $ex) {
            if ( ! is_null($exceptionCallback)) {
                $exceptionCallback($ex);
            }
            $this->release(60);
        } catch (CampaignPausedException $ex) {
            if ( ! is_null($exceptionCallback)) {
                $exceptionCallback($ex);
            }
            $this->campaign->pause($ex->getMessage());

        } catch (Throwable $ex) {
            if ( ! is_null($exceptionCallback)) {
                $exceptionCallback($ex);
            }
            $message = sprintf("Error sending to [%s]. Error: %s", $this->sendData->phone, $ex->getMessage());
            if ($this->stopOnError) {
                $this->campaign->setError($message);
            }
        }

    }
}
