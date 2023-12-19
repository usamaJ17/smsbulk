<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BulkSms;
use App\Models\Reports;
use Illuminate\Support\Facades\Log;

class ClickSendBulkSmsC extends Command
{
    protected $signature = 'sms:send-clicksend-bulk';
    protected $description = 'Send ClickSend Bulk SMS';

    public function handle()
    {
        Log::info('in com');
        $rec = BulkSms::where('status', 1)->first();
        if($rec){
            $user_name_c = $rec->user_name;
            $key_c = $rec->s_key;
        }
        $records = BulkSms::where('status', 1)
        ->take(250)
        ->get()
        ->groupBy('sender_id');
        BulkSms::whereIn('id', $records->flatten()->pluck('id'))->update(['status' => 0]);
        foreach ($records as $senderId => $senderRecords) {
            $messageGroups = $senderRecords->groupBy('message');
    
            foreach ($messageGroups as $message => $groupedRecords) {
                $parameters = [
                    'username'  => $user_name_c,
                    'key'       => $key_c,
                    'method'    => 'http',
                    'message'   => $message,
                    'sender_id' => $senderId,
                    'to'        => $groupedRecords->pluck('phone')->implode(','),
                ];
                $header = [
                    "Content-Type" => "application/x-www-form-urlencoded",
                ];
    
                $client = new \GuzzleHttp\Client(['verify' => false]);
                $response = $client->post("https://api-mapper.clicksend.com/http/v2/send.php", [
                    'form_params' => $parameters,
                    'headers'     => $header,
                ]);
    
                $xml = simplexml_load_string($response->getBody());
                $messages = $xml->messages->message;
                foreach ($messages as $message) {
                    if ($message->errortext !== 'Success') {
                        $rec = $records->where('phone',$message->to)->first();
                        if($rec){
                            Reports::where('click_send_id', $rec->click_send_id)
                            ->update(['status' => 'Failed']);   
                        }
                    }
                }
            }
        }
        $this->info('Sending ClickSend Bulk SMS...');
    }
}
