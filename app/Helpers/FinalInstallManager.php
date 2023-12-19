<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class FinalInstallManager
{
    /**
     * Run final commands.
     *
     * @return string
     */
    public function runFinal()
    {
        $outputLog = new BufferedOutput;

        $this->generateKey($outputLog);
        $this->publishVendorAssets($outputLog);

        return $outputLog->fetch();
    }

    /**
     * Generate New Application Key.
     *
     * @param  BufferedOutput  $outputLog
     *
     * @return void
     */
    private static function generateKey(BufferedOutput $outputLog): void
    {
        try {
            if (config('installer.final.key')) {
                Artisan::call('key:generate', ['--force'=> true], $outputLog);
            }
        } catch (Exception $e) {
            static::response($e->getMessage(), $outputLog);

            return;
        }

    }

    /**
     * Publish vendor assets.
     *
     * @param  BufferedOutput  $outputLog
     *
     * @return void
     */
    private static function publishVendorAssets(BufferedOutput $outputLog): void
    {
        try {
            if (config('installer.final.publish')) {
                Artisan::call('vendor:publish', ['--all' => true], $outputLog);
            }
        } catch (Exception $e) {
            static::response($e->getMessage(), $outputLog);

            return;
        }

    }

    /**
     * Return a formatted error messages.
     *
     * @param $message
     * @param  BufferedOutput $outputLog
     *
     * @return void
     */
    private static function response($message, BufferedOutput $outputLog): void
    {
        [
                'status'      => 'error',
                'message'     => $message,
                'dbOutputLog' => $outputLog->fetch(),
        ];
    }
}
