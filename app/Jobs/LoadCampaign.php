<?php

namespace App\Jobs;

use App\Library\Contracts\CampaignInterface;
use App\Library\Traits\Trackable;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LoadCampaign implements ShouldQueue
{
    use Trackable, Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout       = 7200;
    public $failOnTimeout = true;
    public $tries         = 1;
    public $maxExceptions = 1;

    protected CampaignInterface $campaign;

    /**
     * Create a new job instance.
     */
    public function __construct(CampaignInterface $campaign)
    {
        $this->campaign = $campaign;
    }

    /**
     * Execute the job.
     *
     * @throws Exception
     */
    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $this->campaign->setSending();

        $loadLimit = 100 + rand(1, 9);


        if ($this->campaign->upload_type == 'file') {
            $this->campaign->loadBulkDeliveryJobs(function (ShouldQueue $deliveryJob) {
                $this->batch()->add($deliveryJob);
            }, $loadLimit);
        } else {
            $this->campaign->loadDeliveryJobs(function (ShouldQueue $deliveryJob) {
                $this->batch()->add($deliveryJob);
            }, $loadLimit);
        }


    }
}
