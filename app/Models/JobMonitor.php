<?php

namespace App\Models;

use App\Library\Lockable;
use Exception;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Bus;
use App\Library\Traits\HasUid;

/**
 * @method static where(string $string, string $string1, $uid)
 */
class JobMonitor extends Model
{
    use HasUid;

    const STATUS_QUEUED  = 'queued';
    const STATUS_RUNNING = 'running';
    const STATUS_DONE    = 'done';
    const STATUS_FAILED  = 'failed';


    public static function makeInstance($subject, $jobType): JobMonitor
    {
        $monitor               = new self();
        $monitor->status       = self::STATUS_QUEUED;
        $monitor->subject_name = get_class($subject);
        $monitor->subject_id   = $subject->id;
        $monitor->job_type     = $jobType;

        // Return
        return $monitor;
    }

    public function scopeByJobType($query, $jobType)
    {
        return $query->where('job_type', $jobType);
    }

    public function getBatch(): bool|Batch|null
    {
        if (is_null($this->batch_id)) {
            return false;
        }

        return Bus::findBatch($this->batch_id);
    }

    /**
     * @throws Exception
     */
    public function withExclusiveLock($closure): void
    {
        $lockFile = storage_path('app/lock-job-monitor-'.$this->uid);
        $lock     = new Lockable($lockFile);
        $lock->getExclusiveLock(function () use ($closure) {
            $closure($this->refresh());
        }, 60);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function getJob()
    {
        return $this->job;
    }

    public function hasJob(): bool
    {
        return ! is_null($this->job_id);
    }

    public function hasBatch(): bool
    {
        return ! is_null($this->batch_id);
    }

    public function setFailed($exception): void
    {
        $this->status = self::STATUS_FAILED;
        $errorMsg     = "Error executing job. ".$exception->getMessage();
        $this->error  = $errorMsg;
        $this->save();
    }

    public function setRunning(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->save();
    }

    public function setDone(): void
    {
        $this->status = self::STATUS_DONE;
        $this->save();
    }

    public function setQueued(): void
    {
        $this->status = self::STATUS_QUEUED;
        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_RUNNING, self::STATUS_QUEUED]);
    }

    public function getJsonData()
    {
        // Also convert null or empty string to an empty array ([])
        return json_decode($this->data, true) ?: [];
    }

    public function setJsonData($data): void
    {
        $this->data = json_encode($data);
        $this->save();
    }

    public function updateJsonData($data): void
    {
        $json = $this->getJsonData();
        $json = array_merge($json, $data);
        $this->setJsonData($json);
    }

    public function cancelWithoutDeleteBatch(): void
    {
        $this->cancelBatch();
    }

    public function cancel(): void
    {
        $this->cancelJob(); // if any
        $this->cancelBatch(); // if any

        // For now, do not store cancelled job
        // So, just delete the record
        $this->delete();
    }

    private function cancelJob(): void
    {
        // Get the job record in the `jobs` database table
        $job = $this->getJob();

        // Remove it from queue, if any
        if ( ! is_null($job)) {
            $job->delete();
        }
    }

    private function cancelBatch(): void
    {
        // Then get the batch
        // This is not the batch record in job_batches model
        // So we can just cancel it to have its remaining jobs perish!
        // It will be pruned with queue:prune-batches command
        $batch = $this->getBatch();
        if ( ! is_null($batch)) {
            $batch->cancel();
        }
    }


    public function isDone(): bool
    {
        return $this->status == self::STATUS_DONE;
    }

    public function isFailed(): bool
    {
        return $this->status == self::STATUS_FAILED;
    }

    public function getStatus(): string
    {
        $status = $this->status;

        if ($status == self::STATUS_FAILED) {
            return '<div class="badge bg-danger text-uppercase me-1 mb-1"><span>'.__('locale.labels.failed').'</span></div>';
        }

        if ($status == self::STATUS_QUEUED) {
            return '<div class="badge bg-primary text-uppercase me-1 mb-1"><span>'.__('locale.labels.queued').'</span></div>';
        }

        if ($status == self::STATUS_RUNNING) {
            return '<div class="badge bg-primary text-uppercase me-1 mb-1"><span>'.__('locale.labels.processing').'</span></div>';
        }

        return '<div class="badge bg-success text-uppercase me-1 mb-1"><span>'.__('locale.labels.finished').'</span></div>';
    }


}

