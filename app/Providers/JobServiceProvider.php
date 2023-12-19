<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;

class JobServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        Queue::before(function (JobProcessing $event) {
            $job = $this->getJobObject($event);
            if (property_exists($job, 'monitor')) {
                // 'before' events should be applied to both JOB and BATCH monitor
                $monitor = $job->monitor;
                $monitor->setRunning();
            }
        });

        // mark the SystemJob record as DONE
        Queue::after(function (JobProcessed $event) {
            $job = $this->getJobObject($event);
            if (property_exists($job, 'monitor')) {
                $monitor = $job->monitor;
                if (is_null($monitor->batch_id)) {
                    $monitor->setDone();
                }
            }
        });

        // mark the SystemJob record as FAILED
        Queue::failing(function (JobFailed $event) {
            $job = $this->getJobObject($event);
            if (property_exists($job, 'monitor')) {
                $monitor = $job->monitor;
                if (is_null($monitor->batch_id)) {
                    $monitor->setFailed($event->exception);
                }
            }
        });
    }


    /**
     * Register the application services.
     */
    private function getJobObject($event)
    {
        $data = $event->job->payload();

        return unserialize($data['data']['command']);
    }

}
