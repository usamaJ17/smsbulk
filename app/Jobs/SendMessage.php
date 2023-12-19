<?php

namespace App\Jobs;

use App\Exceptions\CampaignPausedException;
use App\Library\Exception\QuotaExceeded;
use App\Library\QuotaManager;
use App\Models\Contacts;
use DateTime;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;


class SendMessage implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    public int $timeout = 600;
    protected $contact;
    protected $campaign;
    protected $server;
    protected $priceOption;
    protected $triggerId;
    protected $stopOnError = false;

    /**
     * Create a new job instance.
     */
    public function __construct($campaign, Contacts $contact, $server, $priceOption, $triggerId = null)
    {
        $this->campaign    = $campaign;
        $this->contact     = $contact;
        $this->server      = $server;
        $this->priceOption = $priceOption;
        $this->triggerId   = $triggerId;
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

            QuotaManager::with($this->server, 'send')->enforce();
            QuotaManager::with($subscription, 'send')->enforce();

            $sent = $this->campaign->send($this->contact, $this->priceOption, $this->server);
            $this->campaign->track_message($sent, $this->contact, $this->server);

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
            $message = sprintf("Error sending to [%s]. Error: %s", $this->contact, $ex->getMessage());
            if ($this->stopOnError) {
                $this->campaign->setError($message);
            }
        }

    }
}
