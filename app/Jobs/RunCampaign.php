<?php

namespace App\Jobs;

use App\Library\Contracts\CampaignInterface;
use App\Library\Traits\Trackable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunCampaign implements ShouldQueue
{
    use Trackable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected CampaignInterface $campaign;

    public $timeout       = 300;
    public $failOnTimeout = true;
    public $tries         = 1;
    public $maxExceptions = 1;

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
     * @throws Throwable
     */
    public function handle(): void
    {
        if ($this->campaign->isPaused()) {
            return;
        }

        try {
            $this->campaign->run();
        } catch (Throwable $e) {
            $errorMsg = "Error scheduling campaign: ".$e->getMessage()."\n".$e->getTraceAsString();
            $this->campaign->setError($errorMsg);

            // To set the job to failed
            throw $e;
        }

    }
}
