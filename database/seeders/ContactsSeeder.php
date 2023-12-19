<?php

namespace Database\Seeders;

use App\Models\ContactGroups;
use App\Models\Contacts;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('contact_groups')->truncate();
        DB::table('contacts')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        //contact groups
        $contact_groups = [
                [
                        'customer_id'              => 3,
                        'name'                     => 'Codeglen',
                        'sender_id'                => 'codeglen',
                        'send_welcome_sms'         => true,
                        'unsubscribe_notification' => true,
                        'send_keyword_message'     => true,
                        'status'                   => true,
                        'cache'                    => json_encode([
                                'SubscribersCount' => 100,
                        ]),

                ],
                [
                        'customer_id'              => 3,
                        'name'                     => 'USMS',
                        'sender_id'                => null,
                        'send_welcome_sms'         => true,
                        'unsubscribe_notification' => true,
                        'send_keyword_message'     => false,
                        'status'                   => true,
                        'cache'                    => json_encode([
                                'SubscribersCount' => 100,
                        ]),
                ],
                [
                        'customer_id'              => 3,
                        'name'                     => 'SHAMIM',
                        'sender_id'                => null,
                        'send_welcome_sms'         => true,
                        'unsubscribe_notification' => true,
                        'send_keyword_message'     => false,
                        'status'                   => true,
                        'cache'                    => json_encode([
                                'SubscribersCount' => 10,
                        ]),
                ],
        ];

        foreach ($contact_groups as $group) {
            (new ContactGroups)->create($group);
        }

        $factory = Factory::create();
        $data    = [];
        $limit   = 100;
        for ($i = 0; $i < $limit; $i++) {
            $number = '88017'.$i.time();
            $number = substr($number, 0, 13);

            $data[] = [
                    'uid'         => uniqid(),
                    'customer_id' => 3,
                    'group_id'    => 1,
                    'phone'       => $number,
                    'status'      => 'subscribe',
                    'first_name'  => $factory->firstName,
                    'last_name'   => $factory->lastName,
                    'email'       => $factory->email,
                    'company'     => $factory->company,
                    'birth_date'  => Carbon::now()->format('Y-m-d'),
                    'created_at'  => Carbon::now(),
                    'updated_at'  => Carbon::now(),
            ];
        }

        for ($i = 0; $i < $limit; $i++) {
            $number = '88016'.$i.time();
            $number = substr($number, 0, 13);

            $data[] = [
                    'uid'         => uniqid(),
                    'customer_id' => 3,
                    'group_id'    => 2,
                    'phone'       => $number,
                    'status'      => 'subscribe',
                    'first_name'  => $factory->firstName,
                    'last_name'   => $factory->lastName,
                    'email'       => $factory->email,
                    'company'     => $factory->company,
                    'birth_date'  => Carbon::now()->format('Y-m-d'),
                    'created_at'  => Carbon::now(),
                    'updated_at'  => Carbon::now(),
            ];
        }
        for ($i = 0; $i < 10; $i++) {
            $number = '88015'.$i.time();
            $number = substr($number, 0, 13);

            $data[] = [
                    'uid'         => uniqid(),
                    'customer_id' => 3,
                    'group_id'    => 3,
                    'phone'       => $number,
                    'status'      => 'subscribe',
                    'first_name'  => $factory->firstName,
                    'last_name'   => $factory->lastName,
                    'email'       => $factory->email,
                    'company'     => $factory->company,
                    'birth_date'  => Carbon::now()->format('Y-m-d'),
                    'created_at'  => Carbon::now(),
                    'updated_at'  => Carbon::now(),
            ];
        }

        Contacts::insert($data);

    }
}
