<?php

namespace App\Console\Commands;

use App\Http\Controllers\Customer\DLRController;
use App\Models\Reports;
use App\Models\SendingServer;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class DiafaanDLR extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diafaan:dlr';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Diafaan SMS delivery reports';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $sending_servers = SendingServer::where('settings', SendingServer::TYPE_DIAFAAN)->where('status', 1)->get();


        if ($sending_servers->count() > 0) {
            foreach ($sending_servers as $server) {

                $reports = Reports::where('sending_server_id', $server->id)->where('created_at', '>=', Carbon::now()->subMinutes(10))->get();

                foreach ($reports->chunk(100) as $chunk) {
                    foreach ($chunk as $report) {
                        $status = explode('|', $report->status);
                        if (is_array($status) && count($status) > 1 && array_key_exists('1', $status)) {
                            $sms_id     = $status[1];
                            $parameters = [
                                    'username'   => $server->username,
                                    'password'   => $server->password,
                                    'message-id' => $sms_id,
                            ];


                            $sending_url = $server->api_link.'/http/request-status-update?'.http_build_query($parameters);

                            try {
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $sending_url);
                                curl_setopt($ch, CURLOPT_HTTPGET, 1);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                $get_sms_status = curl_exec($ch);

                                if (curl_errno($ch)) {
                                    $get_sms_status = curl_error($ch);
                                } else {
                                    if (str_contains($get_sms_status, '201')) {
                                        $get_sms_status = 'Delivered';
                                    } elseif (str_contains($get_sms_status, '200')) {
                                        $get_sms_status = 'Sent';
                                    } elseif (str_contains($get_sms_status, '301')) {
                                        $get_sms_status = 'Undelivered';
                                    }
                                }
                                curl_close($ch);
                            } catch (Exception $exception) {
                                $get_sms_status = $exception->getMessage();
                            }

                            DLRController::updateDLR($sms_id, $get_sms_status);

                        }
                    }
                }

            }
        }

        return 0;
    }
}
