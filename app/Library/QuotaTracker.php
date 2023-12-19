<?php


namespace App\Library;


use App\Library\Exception\QuotaExceeded;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

class QuotaTracker
{
    protected $filepath;
    protected $mode        = 'minute'; // hour, day, month, year
    protected $separator   = ':';
    protected $blockFormat = [
            'minute' => 'YmdHi',
            'hour'   => 'YmdH00',
            'day'    => 'Ymd0000',
            'month'  => 'Ym000000',
            'year'   => 'Y00000000',
    ];

    protected $quotaExceededCallback;

    public function __construct(string $filepath)
    {
        $this->filepath = $filepath;
        $this->createStorageFile();
    }

    public function whenQuotaExceeded(Closure $callback)
    {
        $this->quotaExceededCallback = $callback;

        return $this;
    }

    /**
     * @throws QuotaExceeded
     * @throws Exception
     */
    public function count(Carbon $now = null, array $limits = [], Closure $callback = null)
    {
        $lock = new Lockable($this->filepath);
        Log::info('-1-');
        $lock->getExclusiveLock(/**
         * @throws QuotaExceeded
         */ function ($fopen) use ($now, $limits, $callback) {
            $now = $now ?: Carbon::now('UTC');

            // Throw an exception if test fails (quota exceeded)
            $this->test($now, $limits, $fopen);

            // Execute callback before writing
            if ( ! is_null($callback)) {
                $callback();
            }

            // Record credits use
            $this->record($now, $fopen);
        }, $timeout = 15);
    }

    private function createStorageFile()
    {
        if ( ! file_exists($this->filepath)) {
            touch($this->filepath);
        }
    }

    /**
     * @throws QuotaExceeded
     * @throws Exception
     */
    private function test(Carbon $now, array $limits, $fopen)
    {
        // [
        //     [
        //         'name' => 'Emails per minute',
        //         'period_unit' => 'minute'
        //         'period_value' => '1'
        //         'limit' => 10,
        //     ], [
        //         'name' => 'Emails per 12 hours',
        //         'period_unit' => 'hour',
        //         'period_value' => '12',
        //         'limit' => 2000
        //     ], [
        //         ....
        //     ]
        // ]
        //
        foreach ($limits as $limit) {
            if (! Str::startsWith($limit['name'], "Plan's")) {
                $period       = sprintf("%s %s", $limit['period_value'], $limit['period_unit']);
                $fromDatetime = $now->copy()->subtract($period);
    
                $creditsUsed = $this->getCreditsUsed($fromDatetime, $now, $fopen);
                if ($creditsUsed >= $limit['limit']) {
                    if ( ! is_null($this->quotaExceededCallback)) {
                        $quotaExceededCallback = $this->quotaExceededCallback;
                        $quotaExceededCallback($limit, $creditsUsed, $now);
                    }
                    throw new QuotaExceeded(sprintf("%s exceeded! %s/%s used", $limit['name'], $creditsUsed, $limit['limit']));
                }   
            }
        }
    }

    private function record(Carbon $now, $fopen)
    {
        $currentBlock = $this->makeBlock($now); // create block for the current date/time
        [$lastBlock, $count] = $this->parseLastRecord($fopen);

        // EMPTY() is safer than IS_NULL()
        if ($currentBlock == $lastBlock) {
            $record = $this->buildRecord($lastBlock, $count + 1);
            $this->updateRecord($record, $fopen);
        } else {
            $record = $this->buildRecord($currentBlock, 1);
            $this->addRecord($record, $fopen);
        }
    }

    private function parseLastRecord($fopen)
    {
        $lastRecord = $this->getLastRecord($fopen);

        return $this->parseBlock($lastRecord);
    }

    private function parseBlock(string $record)
    {
        if (empty($record)) {
            return null;
        }

        return explode($this->separator, $record);
    }

    public function buildRecord($block, $count)
    {
        return "{$block}{$this->separator}{$count}";
    }

    // Convert the provided datetime $now to a string
    public function makeBlock($now)
    {
        $now    = $now ?: Carbon::now('UTC');
        $format = $this->blockFormat[$this->mode];

        return $now->format($format);
    }

    private function getLastRecord($fopen)
    {
        // Find offline
        fseek($fopen, 0, SEEK_END);
        $offset = ftell($fopen) - 1; // Offset values from: -1, 0, 1, 2...

        if ($offset < 0) {
            return ""; // File empty
        }

        fseek($fopen, $offset--); //seek to the end of the line

        // Ignore consecutive empty newlines
        $char = fgetc($fopen);
        while ($offset >= 0 && ($char === "\n")) {
            fseek($fopen, $offset--);
            $char = fgetc($fopen);
        }

        if ($offset < 0) {
            fseek($fopen, 0);

            return trim(fgets($fopen)); // the whole file has Zero or One character (except \n)
        }

        // Continue with offset $offset;
        fseek($fopen, $offset--);
        $char = fgetc($fopen);
        while ($offset >= 0 && $char != "\n") {
            fseek($fopen, $offset--);
            $char = fgetc($fopen);
        }

        if ($offset < 0) { // get to the beginning of file
            fseek($fopen, 0);
        }

        $lastLine = fgets($fopen);

        return trim($lastLine);
    }

    public function updateRecord(string $record, $fopen)
    {
        fseek($fopen, 0, SEEK_END);
        $offset = ftell($fopen) - 1; // Offset values from: -1, 0, 1, 2...

        if ($offset < 0) {
            return ""; // File empty
        }

        fseek($fopen, $offset--); //seek to the end of the line

        // Ignore consecutive empty newlines
        $char = fgetc($fopen);
        while ($offset >= 0 && ($char === "\n")) {
            fseek($fopen, $offset--);
            $char = fgetc($fopen);
        }

        if ($offset < 0) {
            fseek($fopen, 0); // either a leading newline or leading newline + 1char, overwrite leading "\nX" if any
        }

        // Continue with offset $offset;
        fseek($fopen, $offset);
        $char = fgetc($fopen);
        while ($offset >= 0 && $char != "\n") {
            $offset -= 1;
            fseek($fopen, $offset);
            $char = fgetc($fopen);
        }

        if ($offset < 0) { // get to the beginning of file
            fseek($fopen, 0);
        }

        fwrite($fopen, $record);
    }

    public function truncate()
    {
        $fopen = fopen($this->filepath, 'r+');
        fseek($fopen, 0, SEEK_END);
        $offset = ftell($fopen) - 1; // Offset values from: -1, 0, 1, 2...

        if ($offset < 0) {
            return; // File empty
        }

        fseek($fopen, $offset); //seek to the end of the line
        $char = fgetc($fopen);
        while ($offset > 0 && ($char === "\n")) {
            $offset -= 1;
            fseek($fopen, $offset);
            $char = fgetc($fopen);
        }

        ftruncate($fopen, ++$offset);
        fclose($fopen);
    }

    public function addRecord(string $record, $fopen)
    {
        fseek($fopen, 0, SEEK_END);
        $offset = ftell($fopen) - 1; // Offset values from: -1, 0, 1, 2...

        if ($offset < 0) {
            fwrite($fopen, $record);
        } else {
            fseek($fopen, $offset); //seek to the end of the line
            $char = fgetc($fopen);
            while ($offset > 0 && ($char === "\n")) {
                $offset -= 1;
                fseek($fopen, $offset);
                $char = fgetc($fopen);
            }

            fseek($fopen, ++$offset);
            fwrite($fopen, "\n".$record);
        }
    }

    /**
     * @throws Exception
     */
    public function getRecords(Carbon $fromDatetime = null, Carbon $toDatetime = null, $fopen = null)
    {
        $fromDatetime = $fromDatetime ?: Carbon::createFromTimestamp(0, 'UTC'); // Create the earliest date of 1970-01-01
        $toDatetime   = $toDatetime ?: Carbon::now('UTC'); // Current date

        $fromDatetimeStr = $this->makeBlock($fromDatetime);
        $toDatetimeStr   = $this->makeBlock($toDatetime);

        $records = [];

        if (is_null($fopen)) {
            $fopen     = fopen($this->filepath, 'r');
            $closeFile = true;
        } else {
            rewind($fopen);
            $closeFile = false;
        }

        rewind($fopen);
        while ( ! feof($fopen)) {
            $record = trim(fgets($fopen));

            if (empty($record)) {
                break;
            }

            [$block, $count] = $this->parseBlock($record);

            if (empty($block)) {
                throw new Exception("Invalid block {$record}");
            }

            if ($block > $fromDatetimeStr && $block <= $toDatetimeStr) {
                $records[] = [$block, $count];
            }
        }

        if ($closeFile) {
            fclose($fopen);
        }

        // Return
        return $records;
    }

    /**
     * @throws Exception
     */
    public function getCreditsUsed(Carbon $fromDatetime = null, Carbon $toDatetime = null, $fopen = null)
    {
        $records = $this->getRecords($fromDatetime, $toDatetime, $fopen);
        $counts  = array_map(function ($record) {
            [$block, $count] = $record;

            return $count;
        }, $records);

        return array_sum($counts);
    }
}
