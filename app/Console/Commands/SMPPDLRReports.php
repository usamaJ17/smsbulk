<?php

namespace App\Console\Commands;

use App\Http\Controllers\Customer\DLRController;
use App\Models\SendingServer;
use Illuminate\Console\Command;
use Exception;
use smpp\transport\Socket;
use smpp\Client as SmppClient;

class SMPPDLRReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smpp:dlr';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get SMPP DLR Status and Inbound Message';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $sendingServers = SendingServer::select('id', 'api_link', 'port', 'username', 'password')
                                       ->where('status', true)
                                       ->where('type', 'smpp')
                                       ->get();
        foreach ($sendingServers as $server) {
            try {
                SmppClient::$smsNullTerminateOctetstrings = false;
                Socket::$forceIpv4                        = true;
                $transport = new Socket([$server->api_link], $server->port);
                $transport->setRecvTimeout(60000);
                $smppClient = new SmppClient($transport);
                $transport->open();
                $smppClient->bindReceiver($server->username, $server->password);

                $output = $smppClient->readSMS();
            } catch (Exception $e) {
                $output = $e->getMessage();
            }
            /* $smppClient->close();*/

            preg_match('/id:(\d+) .*?stat:([A-Z]+) .*?err:(\d+) /', $output, $matches);

            $id     = $matches[1] ?? null;
            $status = $matches[2] ?? null;
            $err = $matches[3] ?? null;

            $statusMap = [
                    'DELIVRD'     => __('locale.labels.delivered'),
                    'REJECTD'     => __('locale.labels.rejected'),
                    'FAILED'      => __('locale.labels.failed'),
                    'EXPIRED'     => __('locale.labels.failed'),
            ];

            $errMap = [
                    '000'      => __('locale.bsnlerr.succeed'),
                    '023'      => __('locale.bsnlerr.err023'),
                    '025'      => __('locale.bsnlerr.err025'),
                    '027'      => __('locale.bsnlerr.err027'),
                    '070'      => __('locale.bsnlerr.err070'),
                    '103'      => __('locale.bsnlerr.err103'),
                    '107'      => __('locale.bsnlerr.err107'),
                    '109'      => __('locale.bsnlerr.err109'),
                    '252'      => __('locale.bsnlerr.err252'),
                    '253'      => __('locale.bsnlerr.err253'),
                    '600'      => __('locale.bsnlerr.err600'),
                    '601'      => __('locale.bsnlerr.err601'),
                    '602'      => __('locale.bsnlerr.err602'),
                    '603'      => __('locale.bsnlerr.err603'),
                    '604'      => __('locale.bsnlerr.err604'),
                    '605'      => __('locale.bsnlerr.err605'),
                    '606'      => __('locale.bsnlerr.err606'),
                    '609'      => __('locale.bsnlerr.err609'),
                    '610'      => __('locale.bsnlerr.err610'),
                    '611'      => __('locale.bsnlerr.err611'),
                    '612'      => __('locale.bsnlerr.err612'),
                    '613'      => __('locale.bsnlerr.err613'),
                    '619'      => __('locale.bsnlerr.err619'),
                    '620'      => __('locale.bsnlerr.err620'),
                    '621'      => __('locale.bsnlerr.err621'),
                    '622'      => __('locale.bsnlerr.err622'),
                    '623'      => __('locale.bsnlerr.err623'),
                    '624'      => __('locale.bsnlerr.err624'),
                    '629'      => __('locale.bsnlerr.err629'),
                    '630'      => __('locale.bsnlerr.err630'),
                    '631'      => __('locale.bsnlerr.err631'),
                    '632'      => __('locale.bsnlerr.err632'),
                    '633'      => __('locale.bsnlerr.err633'),
                    '634'      => __('locale.bsnlerr.err634'),
                    '635'      => __('locale.bsnlerr.err635'),
                    '637'      => __('locale.bsnlerr.err637'),
                    '638'      => __('locale.bsnlerr.err638'),
                    '649'      => __('locale.bsnlerr.err649'),
                    '650'      => __('locale.bsnlerr.err650'),
                    '651'      => __('locale.bsnlerr.err651'),
                    '652'      => __('locale.bsnlerr.err652'),
                    '653'      => __('locale.bsnlerr.err653'),
                    '659'      => __('locale.bsnlerr.err659'),
                    '660'      => __('locale.bsnlerr.err660'),
                    '661'      => __('locale.bsnlerr.err661'),
                    '669'      => __('locale.bsnlerr.err669'),
                    '670'      => __('locale.bsnlerr.err670'),
                    '671'      => __('locale.bsnlerr.err671'),
                    '699'      => __('locale.bsnlerr.err699'),
            ];

            $status = $statusMap[$status] ?? $status;
            $err = $errMap[$err] ?? $err;

            DLRController::updateDLR($id, $status, $err);
        }
    }
}

