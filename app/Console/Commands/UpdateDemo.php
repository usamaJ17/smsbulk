<?php

namespace App\Console\Commands;

use App\Library\Tool;
use App\Models\AppConfig;
use App\Models\Automation;
use App\Models\Blacklists;
use App\Models\Campaigns;
use App\Models\CampaignsList;
use App\Models\CampaignsSenderid;
use App\Models\ChatBox;
use App\Models\ChatBoxMessage;
use App\Models\ContactGroups;
use App\Models\Contacts;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoices;
use App\Models\Keywords;
use App\Models\Language;
use App\Models\Notifications;
use App\Models\PhoneNumbers;
use App\Models\Plan;
use App\Models\PlansCoverageCountries;
use App\Models\Reports;
use App\Models\Role;
use App\Models\Senderid;
use App\Models\SenderidPlan;
use App\Models\SendingServer;
use App\Models\SpamWord;
use App\Models\Subscription;
use App\Models\SubscriptionLog;
use App\Models\SubscriptionTransaction;
use App\Models\Templates;
use App\Models\TemplateTags;
use App\Models\TrackingLog;
use App\Models\User;
use App\Repositories\Eloquent\EloquentSendingServerRepository;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Demo Database in every day';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {

        AppConfig::where('setting', 'customer_permissions')->update([
                'value' => json_encode([
                        "view_reports",
                        "automations",
                        "view_contact_group",
                        "create_contact_group",
                        "update_contact_group",
                        "delete_contact_group",
                        "view_contact",
                        "create_contact",
                        "update_contact",
                        "delete_contact",
                        "view_numbers",
                        "buy_numbers",
                        "release_numbers",
                        "view_keywords",
                        "create_keywords",
                        "buy_keywords",
                        "update_keywords",
                        "release_keywords",
                        "view_sender_id",
                        "create_sender_id",
                        "delete_sender_id",
                        "view_blacklist",
                        "create_blacklist",
                        "delete_blacklist",
                        "sms_campaign_builder",
                        "sms_quick_send",
                        "voice_campaign_builder",
                        "voice_quick_send",
                        "mms_campaign_builder",
                        "mms_quick_send",
                        "whatsapp_campaign_builder",
                        "whatsapp_quick_send",
                        "viber_campaign_builder",
                        "viber_quick_send",
                        "otp_campaign_builder",
                        "otp_quick_send",
                        "sms_template",
                        "chat_box",
                        "developers",
                        "access_backend",
                ]),
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('languages')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $languages = [
                [
                        'name'     => 'English',
                        'code'     => 'en',
                        'iso_code' => 'us',
                        'status'   => true,
                ],
                [
                        'name'     => 'French',
                        'code'     => 'fr',
                        'iso_code' => 'fr',
                        'status'   => true,
                ],
                [
                        'name'     => 'Chinese',
                        'code'     => 'zh',
                        'iso_code' => 'cn',
                        'status'   => true,
                ],
                [
                        'name'     => 'Spanish',
                        'code'     => 'es',
                        'iso_code' => 'es',
                        'status'   => true,
                ],
                [
                        'name'     => 'Portuguese',
                        'code'     => 'pt',
                        'iso_code' => 'br',
                        'status'   => true,
                ],
                [
                        'name'     => 'Arabic',
                        'code'     => 'ar',
                        'iso_code' => 'sa',
                        'status'   => true,
                ],
                [
                        'name'     => 'Italian',
                        'code'     => 'it',
                        'iso_code' => 'it',
                        'status'   => true,
                ],
                [
                        'name'     => 'Korean',
                        'code'     => 'ko',
                        'iso_code' => 'kr',
                        'status'   => true,
                ],
                [
                        'name'     => 'Slovenian',
                        'code'     => 'sl',
                        'iso_code' => 'sk',
                        'status'   => true,
                ],
        ];

        foreach ($languages as $language) {
            Language::create($language);
        }

        $defaultPassword = '12345678';

        // Create super admin user
        $user     = new User();
        $role     = new Role();
        $customer = new Customer();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $user->truncate();
        $role->truncate();
        $customer->truncate();
        DB::table('role_user')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        /*
         * Create roles
         */

        $superAdminRole = $role->create([
                'name'   => 'administrator',
                'status' => true,
        ]);

        foreach (
                [
                        'access backend',
                        'view customer',
                        'create customer',
                        'edit customer',
                        'delete customer',
                        'view subscription',
                        'new subscription',
                        'manage subscription',
                        'delete subscription',
                        'manage plans',
                        'create plans',
                        'edit plans',
                        'delete plans',
                        'manage currencies',
                        'create currencies',
                        'edit currencies',
                        'delete currencies',
                        'view sending_servers',
                        'create sending_servers',
                        'edit sending_servers',
                        'delete sending_servers',
                        'view keywords',
                        'create keywords',
                        'edit keywords',
                        'delete keywords',
                        'view sender_id',
                        'create sender_id',
                        'edit sender_id',
                        'delete sender_id',
                        'view blacklist',
                        'create blacklist',
                        'edit blacklist',
                        'delete blacklist',
                        'view spam_word',
                        'create spam_word',
                        'edit spam_word',
                        'delete spam_word',
                        'view administrator',
                        'create administrator',
                        'edit administrator',
                        'delete administrator',
                        'view roles',
                        'create roles',
                        'edit roles',
                        'delete roles',
                        'general settings',
                        'system_email settings',
                        'authentication settings',
                        'notifications settings',
                        'localization settings',
                        'pusher settings',
                        'view languages',
                        'new languages',
                        'manage languages',
                        'delete languages',
                        'view payment_gateways',
                        'update payment_gateways',
                        'view email_templates',
                        'update email_templates',
                        'view background_jobs',
                        'view purchase_code',
                        'manage update_application',
                        'manage maintenance_mode',
                        'view invoices',
                        'create invoices',
                        'edit invoices',
                        'delete invoices',
                        'view sms_history',
                        'view block_message',
                        'manage coverage_rates',
                ] as $name) {
            $superAdminRole->permissions()->create(['name' => $name]);
        }

        $authorRole = $role->create([
                'name'   => 'author',
                'status' => true,
        ]);

        foreach (
                [

                        'access backend',
                        'view customer',
                        'create customer',
                        'edit customer',
                        'delete customer',
                        'view subscription',
                        'new subscription',
                        'manage subscription',
                        'delete subscription',
                        'manage plans',
                        'create plans',
                        'edit plans',
                        'delete plans',
                        'manage currencies',
                        'create currencies',
                        'edit currencies',
                        'delete currencies',
                        'view sending_servers',
                        'create sending_servers',
                        'edit sending_servers',
                        'delete sending_servers',
                        'view keywords',
                        'create keywords',
                        'edit keywords',
                        'delete keywords',
                        'view sender_id',
                        'create sender_id',
                        'edit sender_id',
                        'delete sender_id',
                        'view blacklist',
                        'create blacklist',
                        'edit blacklist',
                        'delete blacklist',
                        'view spam_word',
                        'create spam_word',
                        'edit spam_word',
                        'delete spam_word',
                        'view invoices',
                        'create invoices',
                        'edit invoices',
                        'delete invoices',
                        'view sms_history',
                        'view block_message',
                        'manage coverage_rates',
                ] as $name) {
            $authorRole->permissions()->create(['name' => $name]);
        }


        $superAdmin = $user->create([
                'first_name'        => 'Super',
                'last_name'         => 'Admin',
                'image'             => 'app/profile/avatar-1.jpg',
                'email'             => 'admin@codeglen.com',
                'password'          => bcrypt($defaultPassword),
                'status'            => true,
                'is_admin'          => true,
                'active_portal'     => 'admin',
                'locale'            => app()->getLocale(),
                'timezone'          => config('app.timezone'),
                'email_verified_at' => now(),
        ]);

        $superAdmin->api_token = $superAdmin->createToken('admin@codeglen.com')->plainTextToken;
        $superAdmin->save();

        $superAdmin->roles()->save($superAdminRole);


        $supervisor = $user->create([
                'first_name'        => 'Shamim',
                'last_name'         => 'Rahman',
                'image'             => 'app/profile/avatar-4.jpg',
                'email'             => 'shamim97@gmail.com',
                'password'          => bcrypt($defaultPassword),
                'status'            => true,
                'is_admin'          => true,
                'active_portal'     => 'admin',
                'locale'            => app()->getLocale(),
                'timezone'          => config('app.timezone'),
                'email_verified_at' => now(),
        ]);

        $supervisor->api_token = $supervisor->createToken('shamim97@gmail.com')->plainTextToken;
        $supervisor->save();

        $supervisor->roles()->save($authorRole);

        $customers = $user->create([
                'first_name'        => 'Codeglen',
                'last_name'         => null,
                'image'             => 'app/profile/avatar-5.png',
                'email'             => 'client@codeglen.com',
                'password'          => bcrypt($defaultPassword),
                'status'            => true,
                'sms_unit'          => 10000,
                'is_admin'          => false,
                'is_customer'       => true,
                'active_portal'     => 'customer',
                'locale'            => app()->getLocale(),
                'timezone'          => config('app.timezone'),
                'email_verified_at' => now(),
        ]);


        $customers->api_token = $customers->createToken('client@codeglen.com')->plainTextToken;
        $customers->save();

        $customer->create([
                'user_id'            => $customers->id,
                'company'            => 'Codeglen',
                'website'            => 'https://codeglen.com',
                'address'            => 'Banasree, Rampura',
                'city'               => 'Dhaka',
                'postcode'           => '1219',
                'financial_address'  => 'Banasree, Rampura',
                'financial_city'     => 'Dhaka',
                'financial_postcode' => '1219',
                'tax_number'         => '21-4330267',
                'state'              => 'Dhaka',
                'country'            => 'Bangladesh',
                'phone'              => '8801700000000',
                'permissions'        => json_encode([
                        "view_reports",
                        "automations",
                        "view_contact_group",
                        "create_contact_group",
                        "update_contact_group",
                        "delete_contact_group",
                        "view_contact",
                        "create_contact",
                        "update_contact",
                        "delete_contact",
                        "view_numbers",
                        "buy_numbers",
                        "release_numbers",
                        "view_keywords",
                        "create_keywords",
                        "buy_keywords",
                        "update_keywords",
                        "release_keywords",
                        "view_sender_id",
                        "create_sender_id",
                        "delete_sender_id",
                        "view_blacklist",
                        "create_blacklist",
                        "delete_blacklist",
                        "sms_campaign_builder",
                        "sms_quick_send",
                        "voice_campaign_builder",
                        "voice_quick_send",
                        "mms_campaign_builder",
                        "mms_quick_send",
                        "whatsapp_campaign_builder",
                        "whatsapp_quick_send",
                        "viber_campaign_builder",
                        "viber_quick_send",
                        "otp_campaign_builder",
                        "otp_quick_send",
                        "sms_template",
                        "chat_box",
                        "developers",
                        "access_backend",
                ]),
                'notifications'      => json_encode([
                        'login'        => 'no',
                        'sender_id'    => 'yes',
                        'keyword'      => 'yes',
                        'subscription' => 'yes',
                        'promotion'    => 'yes',
                        'profile'      => 'yes',
                ]),
        ]);


        $customer_two = $user->create([
                'first_name'        => 'Afeef Mohammed',
                'last_name'         => "Sa'd",
                'image'             => 'app/profile/avatar-3.jpg',
                'email'             => 'itssaad@gmail.com',
                'password'          => bcrypt($defaultPassword),
                'status'            => true,
                'is_admin'          => false,
                'is_customer'       => true,
                'active_portal'     => 'customer',
                'locale'            => app()->getLocale(),
                'timezone'          => config('app.timezone'),
                'email_verified_at' => now(),
                'created_at'        => Carbon::now()->subMonths(3),
                'updated_at'        => Carbon::now()->subMonths(3),
        ]);

        $customer->create([
                'user_id'            => $customer_two->id,
                'company'            => 'Codeglen',
                'website'            => 'https://codeglen.com',
                'address'            => 'Banasree, Rampura',
                'city'               => 'Dhaka',
                'postcode'           => '1219',
                'financial_address'  => 'Banasree, Rampura',
                'financial_city'     => 'Dhaka',
                'financial_postcode' => '1219',
                'tax_number'         => '21-4330267',
                'state'              => 'Dhaka',
                'country'            => 'Bangladesh',
                'phone'              => '8801700000000',
                'created_at'         => Carbon::now()->subMonths(3),
                'updated_at'         => Carbon::now()->subMonths(3),
                'permissions'        => json_encode([
                        "view_reports",
                        "view_contact_group",
                        "create_contact_group",
                        "update_contact_group",
                        "delete_contact_group",
                        "view_contact",
                        "create_contact",
                        "update_contact",
                        "delete_contact",
                        "view_numbers",
                        "buy_numbers",
                        "release_numbers",
                        "view_keywords",
                        "buy_keywords",
                        "update_keywords",
                        "release_keywords",
                        "view_sender_id",
                        "create_sender_id",
                        "delete_sender_id",
                        "view_blacklist",
                        "create_blacklist",
                        "delete_blacklist",
                        "sms_campaign_builder",
                        "sms_quick_send",
                        "sms_bulk_messages",
                        "voice_campaign_builder",
                        "voice_quick_send",
                        "voice_bulk_messages",
                        "mms_campaign_builder",
                        "mms_quick_send",
                        "mms_bulk_messages",
                        "whatsapp_campaign_builder",
                        "whatsapp_quick_send",
                        "whatsapp_bulk_messages",
                        "sms_template",
                        "chat_box",
                        "developers",
                        "access_backend",
                ]),
                'notifications'      => json_encode([
                        'login'        => 'no',
                        'sender_id'    => 'no',
                        'keyword'      => 'yes',
                        'subscription' => 'yes',
                        'promotion'    => 'no',
                        'profile'      => 'yes',
                ]),
        ]);

        $customer_three = $user->create([
                'first_name'        => 'Abul Kashem',
                'last_name'         => 'Shamim',
                'image'             => 'app/profile/avatar-1.jpg',
                'email'             => 'kashem97@gmail.com',
                'password'          => bcrypt($defaultPassword),
                'status'            => true,
                'is_admin'          => false,
                'is_customer'       => true,
                'active_portal'     => 'customer',
                'locale'            => app()->getLocale(),
                'timezone'          => config('app.timezone'),
                'email_verified_at' => now(),
                'created_at'        => Carbon::now()->subMonths(2),
                'updated_at'        => Carbon::now()->subMonths(2),
        ]);

        $customer->create([
                'user_id'            => $customer_three->id,
                'company'            => 'Codeglen',
                'website'            => 'https://codeglen.com',
                'address'            => 'Banasree, Rampura',
                'city'               => 'Dhaka',
                'postcode'           => '1219',
                'financial_address'  => 'Banasree, Rampura',
                'financial_city'     => 'Dhaka',
                'financial_postcode' => '1219',
                'tax_number'         => '21-4330267',
                'state'              => 'Dhaka',
                'country'            => 'Bangladesh',
                'phone'              => '8801700000000',
                'created_at'         => Carbon::now()->subMonths(2),
                'updated_at'         => Carbon::now()->subMonths(2),
                'permissions'        => json_encode([
                        "view_reports",
                        "view_contact_group",
                        "create_contact_group",
                        "update_contact_group",
                        "delete_contact_group",
                        "view_contact",
                        "create_contact",
                        "update_contact",
                        "delete_contact",
                        "view_numbers",
                        "buy_numbers",
                        "release_numbers",
                        "view_keywords",
                        "buy_keywords",
                        "update_keywords",
                        "release_keywords",
                        "view_sender_id",
                        "create_sender_id",
                        "view_blacklist",
                        "create_blacklist",
                        "delete_blacklist",
                        "sms_campaign_builder",
                        "sms_quick_send",
                        "sms_bulk_messages",
                        "access_backend",
                ]),
                'notifications'      => json_encode([
                        'login'        => 'no',
                        'sender_id'    => 'no',
                        'keyword'      => 'yes',
                        'subscription' => 'yes',
                        'promotion'    => 'no',
                        'profile'      => 'yes',
                ]),
        ]);

        $customer_four = $user->create([
                'first_name'        => 'Jhon',
                'last_name'         => 'Doe',
                'image'             => 'app/profile/avatar-4.jpg',
                'email'             => 'jhon@gmail.com',
                'password'          => bcrypt($defaultPassword),
                'status'            => true,
                'is_admin'          => false,
                'is_customer'       => true,
                'active_portal'     => 'customer',
                'locale'            => app()->getLocale(),
                'timezone'          => config('app.timezone'),
                'email_verified_at' => now(),
                'created_at'        => Carbon::now()->subMonth(),
                'updated_at'        => Carbon::now()->subMonth(),
        ]);

        $customer->create([
                'user_id'            => $customer_four->id,
                'company'            => 'Codeglen',
                'website'            => 'https://codeglen.com',
                'address'            => 'Banasree, Rampura',
                'city'               => 'Dhaka',
                'postcode'           => '1219',
                'financial_address'  => 'Banasree, Rampura',
                'financial_city'     => 'Dhaka',
                'financial_postcode' => '1219',
                'tax_number'         => '21-4330267',
                'state'              => 'Dhaka',
                'country'            => 'Bangladesh',
                'phone'              => '8801700000000',
                'created_at'         => Carbon::now()->subMonth(),
                'updated_at'         => Carbon::now()->subMonth(),
                'permissions'        => json_encode([
                        "view_reports",
                        "view_contact_group",
                        "create_contact_group",
                        "update_contact_group",
                        "delete_contact_group",
                        "view_contact",
                        "create_contact",
                        "update_contact",
                        "delete_contact",
                        "view_numbers",
                        "buy_numbers",
                        "release_numbers",
                        "view_keywords",
                        "buy_keywords",
                        "update_keywords",
                        "release_keywords",
                        "view_sender_id",
                        "create_sender_id",
                        "view_blacklist",
                        "create_blacklist",
                        "delete_blacklist",
                        "sms_campaign_builder",
                        "sms_quick_send",
                        "sms_bulk_messages",
                        "access_backend",
                ]),
                'notifications'      => json_encode([
                        'login'        => 'no',
                        'sender_id'    => 'no',
                        'keyword'      => 'yes',
                        'subscription' => 'yes',
                        'promotion'    => 'no',
                        'profile'      => 'yes',
                ]),
        ]);

        $customer_five = $user->create([
                'first_name'        => 'Sara',
                'last_name'         => 'Doe',
                'image'             => null,
                'email'             => 'sara@gmail.com',
                'password'          => bcrypt($defaultPassword),
                'status'            => true,
                'is_admin'          => false,
                'is_customer'       => true,
                'active_portal'     => 'customer',
                'locale'            => app()->getLocale(),
                'timezone'          => config('app.timezone'),
                'email_verified_at' => now(),
                'created_at'        => Carbon::now()->subMonth(),
                'updated_at'        => Carbon::now()->subMonth(),
        ]);

        $customer->create([
                'user_id'            => $customer_five->id,
                'company'            => 'Codeglen',
                'website'            => 'https://codeglen.com',
                'address'            => 'Banasree, Rampura',
                'city'               => 'Dhaka',
                'postcode'           => '1219',
                'financial_address'  => 'Banasree, Rampura',
                'financial_city'     => 'Dhaka',
                'financial_postcode' => '1219',
                'tax_number'         => '21-4330267',
                'state'              => 'Dhaka',
                'country'            => 'Bangladesh',
                'phone'              => '8801700000000',
                'created_at'         => Carbon::now()->subMonth(),
                'updated_at'         => Carbon::now()->subMonth(),
                'permissions'        => json_encode([
                        "view_reports",
                        "view_contact_group",
                        "create_contact_group",
                        "update_contact_group",
                        "delete_contact_group",
                        "view_contact",
                        "create_contact",
                        "update_contact",
                        "delete_contact",
                        "view_numbers",
                        "buy_numbers",
                        "release_numbers",
                        "view_keywords",
                        "buy_keywords",
                        "update_keywords",
                        "release_keywords",
                        "view_sender_id",
                        "create_sender_id",
                        "view_blacklist",
                        "create_blacklist",
                        "delete_blacklist",
                        "sms_campaign_builder",
                        "sms_quick_send",
                        "sms_bulk_messages",
                        "access_backend",
                ]),
                'notifications'      => json_encode([
                        'login'        => 'no',
                        'sender_id'    => 'no',
                        'keyword'      => 'yes',
                        'subscription' => 'yes',
                        'promotion'    => 'no',
                        'profile'      => 'yes',
                ]),
        ]);


        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('blacklists')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $blacklists = [
                [
                        'user_id' => 1,
                        'number'  => '8801721970156',
                        'reason'  => null,
                ],
                [
                        'user_id' => 1,
                        'number'  => '8801921970156',
                        'reason'  => strtoupper('stop promotion'),
                ],
                [
                        'user_id' => 1,
                        'number'  => '8801520970156',
                        'reason'  => strtoupper('stop promotion'),
                ],
                [
                        'user_id' => 1,
                        'number'  => '8801781970156',
                        'reason'  => strtoupper('stop promotion'),
                ],
                [
                        'user_id' => 3,
                        'number'  => '8801621970156',
                        'reason'  => 'SPAMMING',
                ],
                [
                        'user_id' => 3,
                        'number'  => '8801721970156',
                        'reason'  => null,
                ],
                [
                        'user_id' => 3,
                        'number'  => '8801821970156',
                        'reason'  => strtoupper('stop promotion'),
                ],
                [
                        'user_id' => 3,
                        'number'  => '8801741970156',
                        'reason'  => null,
                ],
                [
                        'user_id' => 3,
                        'number'  => '8801851970156',
                        'reason'  => strtoupper('stop promotion'),
                ],
        ];

        foreach ($blacklists as $blacklist) {
            Blacklists::create($blacklist);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('currencies')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');


        $currency_data = [
                [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'US Dollar',
                        'code'    => 'USD',
                        'format'  => '${PRICE}',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'EURO',
                        'code'    => 'EUR',
                        'format'  => '€{PRICE}',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'British Pound',
                        'code'    => 'GBP',
                        'format'  => '£{PRICE}',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'Japanese Yen',
                        'code'    => 'JPY',
                        'format'  => '¥{PRICE}',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'Russian Ruble',
                        'code'    => 'RUB',
                        'format'  => '‎₽{PRICE}',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'Vietnam Dong',
                        'code'    => 'VND',
                        'format'  => '{PRICE}₫',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'Brazilian Real',
                        'code'    => 'BRL',
                        'format'  => '‎R${PRICE}',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'Bangladeshi Taka',
                        'code'    => 'BDT',
                        'format'  => '‎৳{PRICE}',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'Canadian Dollar',
                        'code'    => 'CAD',
                        'format'  => '‎C${PRICE}',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'Indian rupee',
                        'code'    => 'INR',
                        'format'  => '‎₹{PRICE}',
                        'status'  => true,
                ], [
                        'uid'     => uniqid(),
                        'user_id' => 1,
                        'name'    => 'Nigerian Naira',
                        'code'    => 'CBN',
                        'format'  => '‎₦{PRICE}',
                        'status'  => true,
                ],
        ];

        foreach ($currency_data as $data) {
            Currency::create($data);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('keywords')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $keywords = [
                [
                        'user_id'          => 1,
                        'currency_id'      => 1,
                        'title'            => '50% OFF',
                        'keyword_name'     => '50OFF',
                        'sender_id'        => 'Codeglen',
                        'reply_text'       => 'you will get 50% from our next promotion.',
                        'reply_voice'      => 'you will get 50% from our next promotion.',
                        'price'            => 10,
                        'billing_cycle'    => 'yearly',
                        'frequency_amount' => '1',
                        'frequency_unit'   => 'year',
                        'status'           => 'available',
                ],
                [
                        'user_id'          => 3,
                        'currency_id'      => 2,
                        'title'            => 'CR7',
                        'keyword_name'     => 'CR7',
                        'sender_id'        => 'Codeglen',
                        'reply_text'       => 'Thank you for voting Cristiano Ronaldo.',
                        'reply_voice'      => 'Thank you for voting Cristiano Ronaldo.',
                        'price'            => 10,
                        'billing_cycle'    => 'yearly',
                        'frequency_amount' => '1',
                        'frequency_unit'   => 'year',
                        'validity_date'    => Carbon::now()->add(1, 'year'),
                        'status'           => 'assigned',
                ],
                [
                        'user_id'          => 3,
                        'currency_id'      => 3,
                        'title'            => 'MESSI10',
                        'keyword_name'     => 'MESSI10',
                        'sender_id'        => 'Codeglen',
                        'reply_text'       => 'Thank you for voting Leonel Messi.',
                        'reply_voice'      => 'Thank you for voting Leonel Messi.',
                        'price'            => 10,
                        'billing_cycle'    => 'yearly',
                        'frequency_amount' => '1',
                        'frequency_unit'   => 'year',
                        'validity_date'    => Carbon::yesterday(),
                        'status'           => 'expired',
                ],
                [
                        'user_id'          => 1,
                        'currency_id'      => 1,
                        'title'            => '999',
                        'keyword_name'     => '999',
                        'sender_id'        => 'Codeglen',
                        'reply_text'       => 'You will receive all govt facilities from now.',
                        'reply_voice'      => 'You will receive all govt facilities from now.',
                        'price'            => 10,
                        'billing_cycle'    => 'yearly',
                        'frequency_amount' => '1',
                        'frequency_unit'   => 'year',
                        'status'           => 'available',
                ],
        ];

        foreach ($keywords as $keyword) {
            Keywords::create($keyword);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('phone_numbers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $phone_numbers = [
                [
                        'user_id'          => 1,
                        'number'           => '8801721970168',
                        'status'           => 'available',
                        'capabilities'     => json_encode(['sms', 'voice', 'mms', 'whatsapp']),
                        'price'            => 5,
                        'billing_cycle'    => 'monthly',
                        'frequency_amount' => 1,
                        'frequency_unit'   => 'month',
                        'currency_id'      => 1,
                ],
                [
                        'user_id'          => 3,
                        'number'           => '8801921970168',
                        'status'           => 'assigned',
                        'capabilities'     => json_encode(['sms', 'voice', 'mms', 'whatsapp']),
                        'price'            => 5,
                        'billing_cycle'    => 'monthly',
                        'frequency_amount' => 1,
                        'frequency_unit'   => 'month',
                        'currency_id'      => 2,
                        'validity_date'    => Carbon::now()->addMonth(),
                ],
                [
                        'user_id'          => 3,
                        'number'           => '8801621970168',
                        'status'           => 'expired',
                        'price'            => 5,
                        'capabilities'     => json_encode(['voice', 'mms', 'whatsapp']),
                        'billing_cycle'    => 'custom',
                        'frequency_amount' => 6,
                        'frequency_unit'   => 'month',
                        'currency_id'      => 3,
                ],
                [
                        'user_id'          => 1,
                        'number'           => '8801521970168',
                        'status'           => 'available',
                        'price'            => 5,
                        'capabilities'     => json_encode(['sms', 'voice', 'whatsapp']),
                        'billing_cycle'    => 'yearly',
                        'frequency_amount' => 1,
                        'frequency_unit'   => 'year',
                        'currency_id'      => 1,
                ],
                [
                        'user_id'          => 3,
                        'number'           => '8801821970168',
                        'status'           => 'assigned',
                        'price'            => 5,
                        'capabilities'     => json_encode(['sms', 'voice', 'mms']),
                        'billing_cycle'    => 'monthly',
                        'frequency_amount' => 6,
                        'frequency_unit'   => 'month',
                        'currency_id'      => 3,
                        'validity_date'    => Carbon::now()->add('month', 6),
                ],
        ];

        foreach ($phone_numbers as $number) {
            PhoneNumbers::create($number);
        }


        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('senderid')->truncate();
        DB::table('senderid_plans')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $sender_ids = [
                [
                        'user_id'          => 3,
                        'sender_id'        => 'USMS',
                        'status'           => 'active',
                        'price'            => 5,
                        'billing_cycle'    => 'yearly',
                        'frequency_amount' => '1',
                        'frequency_unit'   => 'year',
                        'currency_id'      => 1,
                ],
                [
                        'user_id'          => 3,
                        'sender_id'        => 'Apple',
                        'status'           => 'payment_required',
                        'price'            => 5,
                        'billing_cycle'    => 'yearly',
                        'frequency_amount' => '1',
                        'frequency_unit'   => 'year',
                        'currency_id'      => 1,
                ],
                [
                        'user_id'          => 3,
                        'sender_id'        => 'Info',
                        'status'           => 'expired',
                        'price'            => 5,
                        'billing_cycle'    => 'yearly',
                        'frequency_amount' => '1',
                        'frequency_unit'   => 'year',
                        'currency_id'      => 1,
                ],
                [
                        'user_id'          => 3,
                        'sender_id'        => 'Police',
                        'status'           => 'block',
                        'price'            => 5,
                        'billing_cycle'    => 'monthly',
                        'frequency_amount' => 1,
                        'frequency_unit'   => 'month',
                        'currency_id'      => 1,
                ],
                [
                        'user_id'          => 3,
                        'sender_id'        => 'SHAMIM',
                        'status'           => 'pending',
                        'price'            => 5,
                        'billing_cycle'    => 'custom',
                        'frequency_amount' => 6,
                        'frequency_unit'   => 'month',
                        'currency_id'      => 1,
                ],
                [
                        'user_id'          => 3,
                        'sender_id'        => 'Codeglen',
                        'status'           => 'active',
                        'price'            => 5,
                        'billing_cycle'    => 'monthly',
                        'frequency_amount' => 1,
                        'frequency_unit'   => 'month',
                        'currency_id'      => 1,
                ],
        ];

        foreach ($sender_ids as $senderId) {
            Senderid::create($senderId);
        }
        $sender_ids_plan = [
                [
                        'price'            => 5,
                        'billing_cycle'    => 'monthly',
                        'frequency_amount' => '1',
                        'frequency_unit'   => 'month',
                        'currency_id'      => 1,
                ],
                [
                        'price'            => 12,
                        'billing_cycle'    => 'custom',
                        'frequency_amount' => '3',
                        'frequency_unit'   => 'month',
                        'currency_id'      => 1,
                ],
                [
                        'price'            => 20,
                        'billing_cycle'    => 'custom',
                        'frequency_amount' => '6',
                        'frequency_unit'   => 'month',
                        'currency_id'      => 1,
                ],
                [
                        'price'            => 35,
                        'billing_cycle'    => 'yearly',
                        'frequency_amount' => '1',
                        'frequency_unit'   => 'year',
                        'currency_id'      => 1,
                ],
                [
                        'price'            => 65,
                        'billing_cycle'    => 'custom',
                        'frequency_amount' => '2',
                        'frequency_unit'   => 'year',
                        'currency_id'      => 1,
                ],
                [
                        'price'            => 115,
                        'billing_cycle'    => 'custom',
                        'frequency_amount' => '3',
                        'frequency_unit'   => 'year',
                        'currency_id'      => 1,
                ],
        ];

        foreach ($sender_ids_plan as $plan) {
            SenderidPlan::create($plan);
        }


        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('sending_servers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $sendingServersRepo = new EloquentSendingServerRepository(new SendingServer());
        $sendingServers     = collect($sendingServersRepo->allSendingServer());

        foreach ($sendingServers->reverse() as $server) {
            $server['user_id'] = 1;
            $server['status']  = true;

            SendingServer::create($server);
        }


        SpamWord::truncate();

        $spam_words = [
                [
                        'word' => 'POLICE',
                ],
                [
                        'word' => 'RAB',
                ],
                [
                        'word' => 'GOVT',
                ],
                [
                        'word' => 'NYPD',
                ],
                [
                        'word' => 'CIA',
                ],
                [
                        'word' => 'NDP',
                ],
                [
                        'word' => 'FBI',
                ],
        ];

        foreach ($spam_words as $word) {
            SpamWord::create($word);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('plans')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $plans = [
                [
                        'user_id'              => 1,
                        'currency_id'          => 1,
                        'name'                 => 'Free',
                        'description'          => 'A simple start for everyone',
                        'price'                => '0',
                        'billing_cycle'        => 'monthly',
                        'frequency_amount'     => 1,
                        'frequency_unit'       => 'month',
                        'options'              => '{"sms_max":"10","whatsapp_max":"10","list_max":"5","subscriber_max":"500","subscriber_per_list_max":"100","segment_per_list_max":"3","billing_cycle":"monthly","sending_limit":"100_per_minute","sending_quota":100,"sending_quota_time":1,"sending_quota_time_unit":"minute","max_process":"1","unsubscribe_url_required":"yes","list_import":"yes","list_export":"yes","api_access":"no","create_sub_account":"no","delete_sms_history":"no","add_previous_balance":"no","sender_id_verification":"yes","send_spam_message":"no","quota_value":100,"quota_base":1,"quota_unit":"minute","sender_id":"Codeglen","sender_id_price":"0","sender_id_billing_cycle":"monthly","sender_id_frequency_amount":1,"sender_id_frequency_unit":"month"}',
                        'status'               => true,
                        'tax_billing_required' => false,
                ],
                [
                        'user_id'              => 1,
                        'currency_id'          => 1,
                        'name'                 => 'Standard',
                        'description'          => 'For small to medium businesses',
                        'price'                => '49',
                        'billing_cycle'        => 'custom',
                        'frequency_amount'     => 6,
                        'frequency_unit'       => 'month',
                        'is_popular'           => true,
                        'options'              => '{"sms_max":"1000","whatsapp_max":"1000","list_max":"-1","subscriber_max":"-1","subscriber_per_list_max":"-1","segment_per_list_max":"-1","billing_cycle":"monthly","sending_limit":"10000_per_hour","sending_quota":"1000","sending_quota_time":"1","sending_quota_time_unit":"hour","max_process":"2","unsubscribe_url_required":"yes","list_import":"yes","list_export":"yes","api_access":"yes","create_sub_account":"yes","delete_sms_history":"yes","add_previous_balance":"yes","sender_id_verification":"yes","send_spam_message":"yes","quota_value":10000,"quota_base":1,"quota_unit":"hour","sender_id":"USMS","sender_id_price":"5","sender_id_billing_cycle":"monthly","sender_id_frequency_amount":1,"sender_id_frequency_unit":"month"}',
                        'status'               => true,
                        'tax_billing_required' => true,
                ],
                [
                        'user_id'              => 1,
                        'currency_id'          => 1,
                        'name'                 => 'Enterprise',
                        'description'          => 'Solution for big organizations',
                        'price'                => '99',
                        'billing_cycle'        => 'monthly',
                        'frequency_amount'     => 1,
                        'frequency_unit'       => 'month',
                        'options'              => '{"sms_max":"-1","whatsapp_max":"-1","list_max":"-1","subscriber_max":"-1","subscriber_per_list_max":"-1","segment_per_list_max":"-1","billing_cycle":"monthly","sending_limit":"50000_per_hour","sending_quota":"1000","sending_quota_time":"1","sending_quota_time_unit":"hour","max_process":"3","unsubscribe_url_required":"yes","list_import":"yes","list_export":"yes","api_access":"yes","create_sub_account":"yes","delete_sms_history":"yes","add_previous_balance":"yes","sender_id_verification":"yes","send_spam_message":"yes","quota_value":50000,"quota_base":1,"quota_unit":"hour","per_unit_price":"0.3","sender_id":"Codeglen","sender_id_price":"5","sender_id_billing_cycle":"monthly","sender_id_frequency_amount":1,"sender_id_frequency_unit":"month"}',
                        'status'               => true,
                        'tax_billing_required' => true,
                ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }


        $plan_coverage = [
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 16,
                        'plan_id'        => 2,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"2","receive_voice_sms":"1","mms_sms":"3","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 92,
                        'plan_id'        => 2,
                        'sending_server' => 2,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 147,
                        'plan_id'        => 2,
                        'sending_server' => 3,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 193,
                        'plan_id'        => 2,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 220,
                        'plan_id'        => 2,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 221,
                        'plan_id'        => 2,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 122,
                        'plan_id'        => 2,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 146,
                        'plan_id'        => 2,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 192,
                        'plan_id'        => 2,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 190,
                        'plan_id'        => 2,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 16,
                        'plan_id'        => 3,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 221,
                        'plan_id'        => 3,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 16,
                        'plan_id'        => 1,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
                [
                        'uid'            => Str::uuid(),
                        'country_id'     => 221,
                        'plan_id'        => 1,
                        'sending_server' => 1,
                        'status'         => true,
                        'options'        => '{"plain_sms":"1","receive_plain_sms":"1","voice_sms":"1","receive_voice_sms":"1","mms_sms":"1","receive_mms_sms":"1","whatsapp_sms":"1","receive_whatsapp_sms":"1","viber_sms":"1","receive_viber_sms":"1","otp_sms":"1","receive_otp_sms":"1"}',
                        'created_at'     => now(),
                        'updated_at'     => now(),
                ],
        ];


        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('plans_coverage_countries')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        PlansCoverageCountries::insert($plan_coverage);


        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('subscriptions')->truncate();
        DB::table('subscription_logs')->truncate();
        DB::table('subscription_transactions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');


        $subscriptions = [
                [
                        'uid'                    => uniqid(),
                        'user_id'                => 3,
                        'plan_id'                => 2,
                        'options'                => '{"credit_warning":true,"credit":"100","credit_notify":"both","subscription_warning":true,"subscription_notify":"both"}',
                        'start_at'               => now(),
                        'status'                 => 'active',
                        'end_period_last_days'   => 10,
                        'current_period_ends_at' => Carbon::now()->addMonths(6),
                        'end_at'                 => null,
                        'end_by'                 => null,
                        'created_at'             => now(),
                        'updated_at'             => now(),
                ], [
                        'uid'                    => uniqid(),
                        'user_id'                => 4,
                        'plan_id'                => 3,
                        'start_at'               => now(),
                        'status'                 => 'active',
                        'options'                => '{"credit_warning":true,"credit":"100","credit_notify":"both","subscription_warning":true,"subscription_notify":"both"}',
                        'end_period_last_days'   => 10,
                        'current_period_ends_at' => Carbon::now()->addYear(),
                        'end_at'                 => null,
                        'end_by'                 => null,
                        'created_at'             => now(),
                        'updated_at'             => now(),
                ],
        ];


        Subscription::insert($subscriptions);

        $subscriptionLogs = [
                [
                        'subscription_id' => 1,
                        'type'            => 'admin_plan_assigned',
                        'data'            => '{"plan":"Standard","price":"\u00a3500"}',
                        'created_at'      => now(),
                        'updated_at'      => now(),
                ],
                [
                        'subscription_id' => 2,
                        'type'            => 'admin_plan_assigned',
                        'data'            => '{"plan":"Premium","price":"$5,000"}',
                        'created_at'      => now(),
                        'updated_at'      => now(),
                ],
        ];

        SubscriptionLog::insert($subscriptionLogs);

        $subscriptionTransaction = [
                [
                        'subscription_id' => 1,
                        'title'           => 'Subscribed to Standard plan',
                        'type'            => 'subscribe',
                        'status'          => 'success',
                        'amount'          => '£500',
                        'created_at'      => now(),
                        'updated_at'      => now(),
                ],
                [
                        'subscription_id' => 2,
                        'title'           => 'Subscribed to Premium plan',
                        'type'            => 'subscribe',
                        'status'          => 'success',
                        'amount'          => '$5,000',
                        'created_at'      => now(),
                        'updated_at'      => now(),
                ],
        ];

        SubscriptionTransaction::insert($subscriptionTransaction);

        //create template tag
        DB::table('template_tags')->truncate();
        $template_tags = [
                [
                        'name'     => 'State',
                        'tag'      => 'state',
                        'type'     => 'text',
                        'required' => 0,
                ],
                [
                        'name'     => 'Event Date',
                        'tag'      => 'event_date',
                        'type'     => 'date',
                        'required' => 0,
                ],
                [
                        'name'     => 'Website',
                        'tag'      => 'website',
                        'type'     => 'url',
                        'required' => 0,
                ],
        ];

        foreach ($template_tags as $tags) {
            TemplateTags::create($tags);
        }

        //invoice
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('invoices')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $invoices = [
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'senderid',
                        'description'    => 'Payment for Sender ID Apple',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDays(3),
                        'updated_at'     => Carbon::now()->subDays(3),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'keyword',
                        'description'    => 'Payment for keyword CR7',
                        'transaction_id' => 'pi_1Id6n9Jer'.uniqid(),
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDays(3),
                        'updated_at'     => Carbon::now()->subDays(3),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'keyword',
                        'description'    => 'Payment for keyword MESSI10',
                        'transaction_id' => 'pi_1Id6n9Jer'.uniqid(),
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDays(2),
                        'updated_at'     => Carbon::now()->subDays(2),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 500,
                        'type'           => 'subscription',
                        'description'    => 'Payment for subscription Premium',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDays(2),
                        'updated_at'     => Carbon::now()->subDays(2),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 500,
                        'type'           => 'subscription',
                        'description'    => 'Payment for subscription Standard',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDays(2),
                        'updated_at'     => Carbon::now()->subDays(2),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'number',
                        'description'    => 'Payment for number',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDays(2),
                        'updated_at'     => Carbon::now()->subDays(2),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'senderid',
                        'description'    => 'Payment for Sender ID Info',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDays(2),
                        'updated_at'     => Carbon::now()->subDays(2),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'keyword',
                        'description'    => 'Payment for Keyword Apple',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDay(),
                        'updated_at'     => Carbon::now()->subDay(),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'senderid',
                        'description'    => 'Payment for Sender ID Codeglen',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDay(),
                        'updated_at'     => Carbon::now()->subDay(),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'senderid',
                        'description'    => 'Payment for Sender ID USMS',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDay(),
                        'updated_at'     => Carbon::now()->subDay(),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'senderid',
                        'description'    => 'Payment for Sender ID SHAMIM',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now()->subDay(),
                        'updated_at'     => Carbon::now()->subDay(),
                ],
                [
                        'uid'            => uniqid(),
                        'user_id'        => 3,
                        'currency_id'    => 1,
                        'payment_method' => 3,
                        'amount'         => 50,
                        'type'           => 'number',
                        'description'    => 'Payment for Number 88014754789',
                        'transaction_id' => 'pi_1Id6n9JerTkfRDz2sMhOqnNS',
                        'status'         => 'paid',
                        'created_at'     => Carbon::now(),
                        'updated_at'     => Carbon::now(),
                ],
        ];

        Invoices::insert($invoices);


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

        //sms template
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('templates')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $sms_templates = [
                [
                        'user_id' => 3,
                        'name'    => "Promotion",
                        'message' => 'You will get 50 Percent off from next {event_date}',
                ],
                [
                        'user_id' => 3,
                        'name'    => "Greeting",
                        'message' => 'Hi {first_name}, welcome to {company}',
                ],
        ];

        foreach ($sms_templates as $template) {
            Templates::create($template);
        }


        //campaigns

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('campaigns_senderids')->truncate();
        DB::table('campaigns_lists')->truncate();
        DB::table('campaigns')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $campaigns = [
                [
                        'user_id'       => 3,
                        'campaign_name' => 'SMS Campaign',
                        'message'       => 'Hi {first_name}, welcome to {company}',
                        'sms_type'      => 'plain',
                        'upload_type'   => 'normal',
                        'status'        => 'delivered',
                        'cache'         => '{"ContactCount":100,"DeliveredCount":94,"FailedDeliveredCount":6,"NotDeliveredCount":0}',
                        'run_at'        => Carbon::now()->subMinutes(5),
                        'delivery_at'   => Carbon::now()->subMinutes(2),
                ],
                [
                        'user_id'       => 3,
                        'campaign_name' => 'Voice Campaign',
                        'message'       => 'You will get 50 Percent off from next {event_date}',
                        'sms_type'      => 'voice',
                        'language'      => 'en-GB',
                        'gender'        => 'male',
                        'upload_type'   => 'normal',
                        'status'        => 'queued',
                        'cache'         => '{"ContactCount":100,"DeliveredCount":0,"FailedDeliveredCount":0,"NotDeliveredCount":0}',
                ],
                [
                        'user_id'       => 3,
                        'campaign_name' => 'MMS Campaign',
                        'message'       => 'Hi {first_name}, welcome to {company}',
                        'media_url'     => 'https://ultimatesms.codeglen.com/demo/mms/mms_1617527278.png',
                        'sms_type'      => 'mms',
                        'upload_type'   => 'normal',
                        'status'        => 'queued',
                        'cache'         => '{"ContactCount":100,"DeliveredCount":0,"FailedDeliveredCount":0,"NotDeliveredCount":0}',
                ],
                [
                        'user_id'       => 3,
                        'campaign_name' => 'WhatsApp Campaign',
                        'message'       => 'You will get 50 Percent off from next {event_date}',
                        'sms_type'      => 'whatsapp',
                        'upload_type'   => 'normal',
                        'status'        => 'queued',
                        'cache'         => '{"ContactCount":100,"DeliveredCount":0,"FailedDeliveredCount":0,"NotDeliveredCount":0}',
                ],
                [
                        'user_id'       => 3,
                        'campaign_name' => 'Schedule Campaign',
                        'message'       => 'You will get 50 Percent off from next {event_date}',
                        'sms_type'      => 'plain',
                        'upload_type'   => 'normal',
                        'status'        => 'scheduled',
                        'cache'         => '{"ContactCount":100,"DeliveredCount":0,"FailedDeliveredCount":0,"NotDeliveredCount":0}',
                        'schedule_time' => Carbon::now()->addDays(3),
                        'schedule_type' => 'onetime',
                ],
                [
                        'user_id'          => 3,
                        'campaign_name'    => 'Recurring Campaign',
                        'message'          => 'You will get 50 Percent off from next {event_date}',
                        'sms_type'         => 'plain',
                        'upload_type'      => 'normal',
                        'status'           => 'scheduled',
                        'cache'            => '{"ContactCount":100,"DeliveredCount":0,"FailedDeliveredCount":0,"NotDeliveredCount":0}',
                        'schedule_time'    => Carbon::now()->addDays(2),
                        'schedule_type'    => 'recurring',
                        'frequency_cycle'  => 'monthly',
                        'frequency_amount' => 1,
                        'frequency_unit'   => 'month',
                        'recurring_end'    => Carbon::now()->addMonth(),
                ],
                [
                        'user_id'       => 3,
                        'campaign_name' => 'Normal Campaign',
                        'message'       => 'You will get 50 Percent off from next {event_date}',
                        'sms_type'      => 'plain',
                        'upload_type'   => 'normal',
                        'status'        => 'queued',
                        'cache'         => '{"ContactCount":100,"DeliveredCount":0,"FailedDeliveredCount":0,"NotDeliveredCount":0}',
                ],
                [
                        'user_id'       => 3,
                        'campaign_name' => 'Campaign Paused',
                        'message'       => 'Hi {first_name}, welcome to {company}',
                        'sms_type'      => 'plain',
                        'upload_type'   => 'normal',
                        'status'        => 'paused',
                        'cache'         => '{"ContactCount":100,"DeliveredCount":30,"FailedDeliveredCount":0,"NotDeliveredCount":0}',
                ],
                [
                        'user_id'       => 3,
                        'campaign_name' => 'Campaign Processing',
                        'message'       => 'Hi {first_name}, welcome to {company}',
                        'sms_type'      => 'plain',
                        'upload_type'   => 'normal',
                        'status'        => 'processing',
                        'cache'         => '{"ContactCount":100,"DeliveredCount":30,"FailedDeliveredCount":0,"NotDeliveredCount":0}',
                        'run_at'        => Carbon::now()->subMinutes(5),
                ],
        ];

        foreach ($campaigns as $campaign) {
            Campaigns::create($campaign);
        }

        $campaign_lists = [
                [
                        'campaign_id'     => 1,
                        'contact_list_id' => 1,
                ],
                [
                        'campaign_id'     => 2,
                        'contact_list_id' => 1,
                ],
                [
                        'campaign_id'     => 3,
                        'contact_list_id' => 1,
                ],
                [
                        'campaign_id'     => 4,
                        'contact_list_id' => 2,
                ],
                [
                        'campaign_id'     => 5,
                        'contact_list_id' => 2,
                ],
                [
                        'campaign_id'     => 6,
                        'contact_list_id' => 1,
                ],
                [
                        'campaign_id'     => 7,
                        'contact_list_id' => 1,
                ],
                [
                        'campaign_id'     => 8,
                        'contact_list_id' => 2,
                ],
                [
                        'campaign_id'     => 9,
                        'contact_list_id' => 2,
                ],
        ];

        foreach ($campaign_lists as $list) {
            CampaignsList::create($list);
        }

        $campaign_sender_ids = [
                [
                        'campaign_id' => 1,
                        'sender_id'   => 'USMS',
                        'originator'  => 'sender_id',
                ],
                [
                        'campaign_id' => 2,
                        'sender_id'   => '8801821970168',
                        'originator'  => 'phone_number',
                ],
                [
                        'campaign_id' => 3,
                        'sender_id'   => '8801821970168',
                        'originator'  => 'phone_number',
                ],
                [
                        'campaign_id' => 4,
                        'sender_id'   => 'Codeglen',
                        'originator'  => 'sender_id',
                ],
                [
                        'campaign_id' => 5,
                        'sender_id'   => 'Info',
                        'originator'  => 'sender_id',
                ],
                [
                        'campaign_id' => 6,
                        'sender_id'   => '8801921970168',
                        'originator'  => 'phone_number',
                ],
                [
                        'campaign_id' => 7,
                        'sender_id'   => '8801821970168',
                        'originator'  => 'phone_number',
                ],
                [
                        'campaign_id' => 8,
                        'sender_id'   => 'USMS',
                        'originator'  => 'sender_id',
                ],
                [
                        'campaign_id' => 8,
                        'sender_id'   => '8801821970168',
                        'originator'  => 'phone_number',
                ],
        ];

        foreach ($campaign_sender_ids as $senderId) {
            CampaignsSenderid::create($senderId);
        }

        //reports
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('reports')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $campaign_data = [];
        $contacts      = Contacts::where('group_id', 1)->take(100)->cursor();

        $message = 'Hi {first_name}, welcome to {company}';

        foreach ($contacts as $index => $contact) {
            $render_message = Tool::renderSMS($message, [
                    'first_name' => $contact->first_name,
                    'company'    => $contact->company,
            ]);

            $status            = 'Invalid Token';
            $sending_server_id = 1;
            $campaign_id       = 1;

            if ($index >= 6) {
                $status = 'Delivered';
            }

            $campaign_data[] = [
                    'user_id'           => 3,
                    'from'              => 'USMS',
                    'to'                => $contact->phone,
                    'message'           => $render_message,
                    'sms_type'          => 'plain',
                    'status'            => $status,
                    'send_by'           => 'to',
                    'campaign_id'       => $campaign_id,
                    'cost'              => 1,
                    'sms_count'         => 1,
                    'sending_server_id' => $sending_server_id,
                    'created_at'        => Carbon::now(),
                    'updated_at'        => Carbon::now(),
            ];
        }

        $contacts = Contacts::where('group_id', 2)->take(30)->cursor();

        foreach ($contacts as $contact) {
            $render_message = Tool::renderSMS($message, [
                    'first_name' => $contact->first_name,
                    'company'    => $contact->company,
            ]);

            $campaign_data[] = [
                    'user_id'           => 3,
                    'from'              => 'USMS',
                    'to'                => $contact->phone,
                    'message'           => $render_message,
                    'sms_type'          => 'plain',
                    'status'            => 'Delivered',
                    'send_by'           => 'to',
                    'campaign_id'       => 8,
                    'cost'              => 1,
                    'sms_count'         => 1,
                    'sending_server_id' => 3,
                    'created_at'        => Carbon::now(),
                    'updated_at'        => Carbon::now(),
            ];

            $campaign_data[] = [
                    'user_id'           => 3,
                    'from'              => '8801821970168',
                    'to'                => $contact->phone,
                    'message'           => $render_message,
                    'sms_type'          => 'plain',
                    'status'            => 'Delivered',
                    'send_by'           => 'to',
                    'campaign_id'       => 9,
                    'cost'              => 1,
                    'sms_count'         => 1,
                    'sending_server_id' => 1,
                    'created_at'        => Carbon::now(),
                    'updated_at'        => Carbon::now(),
            ];
        }

        Reports::insert($campaign_data);


        $factory   = Factory::create();
        $sms_types = ['plain', 'plain', 'voice', 'mms', 'whatsapp', 'plain'];
        $send_by   = ['to', 'from', 'from', 'from', 'from', 'api'];

        $data = [];

        for ($j = 0; $j < count($sms_types); $j++) {
            $data = [...$data, ...$this->createDataArray($factory, 100, 'Delivered', $sms_types[$j], $send_by[$j])];
        }

        $statuses = ['failed', 'Delivered'];
        $smsTypes = ['plain', 'voice', 'mms', 'whatsapp'];
        $sendBys  = ['to', 'api', 'from'];

        foreach ($statuses as $status) {
            foreach ($smsTypes as $smsType) {
                foreach ($sendBys as $sendBy) {
                    $limit = ($status === 'Delivered' && $smsType === 'plain' && $sendBy !== 'from') ? 1 : 10;
                    $data  = [...$data, ...$this->createDataArray($factory, $limit, $status, $smsType, $sendBy)];
                }
            }
        }

        Reports::insert($data);


        //Automations

        TrackingLog::whereIn('automation_id', [1, 2])->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('automations')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $automations = [
                [
                        'name'              => 'SayBirthday',
                        'user_id'           => 3,
                        'contact_list_id'   => 1,
                        'sending_server_id' => 1,
                        'timezone'          => 'Asia/Dhaka',
                        'sender_id'         => json_encode(['USMS']),
                        'message'           => 'Hi {first_name}, welcome to {company}',
                        'sms_type'          => 'plain',
                        'status'            => 'active',
                        'cache'             => json_encode([
                                'ContactCount'         => 100,
                                'DeliveredCount'       => 30,
                                'FailedDeliveredCount' => 0,
                                'NotDeliveredCount'    => 0,
                                'PendingContactCount'  => 70,
                        ]),
                        'data'              => json_encode([
                                'options' => [
                                        'before' => 0,
                                        'at'     => '14:21',
                                ],
                        ]),
                ],
                [
                        'name'              => 'BirthdayGreetings',
                        'user_id'           => 3,
                        'contact_list_id'   => 1,
                        'sending_server_id' => 1,
                        'timezone'          => 'Asia/Dhaka',
                        'sender_id'         => json_encode(['USMS']),
                        'message'           => 'Hi {first_name}, welcome to {company}',
                        'sms_type'          => 'plain',
                        'status'            => 'inactive',
                        'cache'             => json_encode([
                                'ContactCount'         => 100,
                                'DeliveredCount'       => 0,
                                'FailedDeliveredCount' => 0,
                                'NotDeliveredCount'    => 0,
                                'PendingContactCount'  => 100,
                        ]),
                        'data'              => json_encode([
                                'options' => [
                                        'before' => 0,
                                        'at'     => '14:21',
                                ],
                        ]),
                ],
        ];

        foreach ($automations as $automation) {
            Automation::create($automation);
        }
        $AutomationContacts = Contacts::where('group_id', 1)->take(30)->get();

        $automationData = [];

        foreach ($AutomationContacts as $contact) {
            $render_message = Tool::renderSMS($message, [
                    'first_name' => $contact->first_name,
                    'company'    => $contact->company,
            ]);

            $automationData[] = [
                    'user_id'           => 3,
                    'from'              => 'USMS',
                    'to'                => $contact->phone,
                    'message'           => $render_message,
                    'sms_type'          => 'plain',
                    'status'            => 'Delivered',
                    'send_by'           => 'to',
                    'automation_id'     => 1,
                    'cost'              => 1,
                    'sms_count'         => 1,
                    'sending_server_id' => 1,
            ];
        }

        $i = 1;
        foreach ($automationData as $aData) {
            $response = Reports::create($aData);

            $params = [
                    'message_id'        => $response->id,
                    'customer_id'       => 3,
                    'sending_server_id' => 1,
                    'automation_id'     => 1,
                    'contact_id'        => $i,
                    'contact_group_id'  => 1,
                    'sms_count'         => 1,
                    'status'            => "Delivered",
            ];

            TrackingLog::create($params);
            $i++;
        }

        Automation::find(1)->update([
                'cache' => json_encode([
                        'ContactCount'         => 100,
                        'DeliveredCount'       => 30,
                        'FailedDeliveredCount' => 0,
                        'NotDeliveredCount'    => 0,
                        'PendingContactCount'  => 70,
                ]),
        ]);


        //chat box
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('chat_box_messages')->truncate();
        DB::table('chat_boxes')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $chat_box = [
                [
                        'user_id'           => 3,
                        'from'              => '8801921970168',
                        'to'                => '8801721970168',
                        'notification'      => '1',
                        'sending_server_id' => '1',
                        'reply_by_customer' => true,
                ],
                [
                        'user_id'           => 3,
                        'from'              => '8801821970168',
                        'to'                => '8801621970168',
                        'notification'      => '0',
                        'sending_server_id' => '1',
                        'reply_by_customer' => true,
                ],
        ];

        foreach ($chat_box as $chat) {
            ChatBox::create($chat);
        }

        $chat_messages = [
                [
                        'box_id'            => 1,
                        'message'           => 'test message',
                        'sms_type'          => 'sms',
                        'send_by'           => 'from',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 2,
                        'message'           => 'another test message',
                        'sms_type'          => 'sms',
                        'send_by'           => 'from',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 2,
                        'message'           => 'another test message reply',
                        'sms_type'          => 'sms',
                        'send_by'           => 'to',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 1,
                        'message'           => 'test message',
                        'sms_type'          => 'sms',
                        'send_by'           => 'from',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 1,
                        'message'           => $factory->text(120),
                        'sms_type'          => 'sms',
                        'send_by'           => 'to',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 2,
                        'message'           => $factory->text(120),
                        'sms_type'          => 'sms',
                        'send_by'           => 'to',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 2,
                        'message'           => $factory->text(120),
                        'sms_type'          => 'sms',
                        'send_by'           => 'from',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 2,
                        'message'           => $factory->text(120),
                        'sms_type'          => 'sms',
                        'send_by'           => 'to',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 2,
                        'message'           => $factory->text(120),
                        'sms_type'          => 'sms',
                        'send_by'           => 'from',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 2,
                        'message'           => $factory->text(120),
                        'sms_type'          => 'sms',
                        'send_by'           => 'from',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 2,
                        'message'           => $factory->text(120),
                        'sms_type'          => 'sms',
                        'send_by'           => 'to',
                        'sending_server_id' => 1,
                ],
                [
                        'box_id'            => 2,
                        'message'           => $factory->text(120),
                        'sms_type'          => 'sms',
                        'send_by'           => 'from',
                        'sending_server_id' => 1,
                ],
        ];

        foreach ($chat_messages as $message) {
            ChatBoxMessage::create($message);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('notifications')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $notifications = [
                [
                        'user_id'           => 1,
                        'notification_for'  => 'admin',
                        'notification_type' => 'user',
                        'message'           => 'Codeglen Registered',
                        'mark_read'         => 0,
                ],
                [
                        'user_id'           => 1,
                        'notification_for'  => 'admin',
                        'notification_type' => 'plan',
                        'message'           => 'Standard Purchased By Codeglen',
                        'mark_read'         => 0,
                ],
                [
                        'user_id'           => 1,
                        'notification_for'  => 'admin',
                        'notification_type' => 'senderid',
                        'message'           => 'New Sender ID request from Codeglen',
                        'mark_read'         => 0,
                ],
                [
                        'user_id'           => 1,
                        'notification_for'  => 'admin',
                        'notification_type' => 'number',
                        'message'           => '8801521970168 Purchased By Codeglen',
                        'mark_read'         => 0,
                ],
                [
                        'user_id'           => 3,
                        'notification_for'  => 'client',
                        'notification_type' => 'chatbox',
                        'message'           => 'Message From 8801521970168',
                        'mark_read'         => 0,
                ],

                [
                        'user_id'           => 3,
                        'notification_for'  => 'admin',
                        'notification_type' => 'senderid',
                        'message'           => 'Sender ID Codeglen Approved',
                        'mark_read'         => 0,
                ],
        ];

        foreach ($notifications as $notification) {
            Notifications::create($notification);
        }

        return 0;

    }


    private function createDataArray($factory, $limit, $status = 'Delivered', $smsType = 'plain', $sendBy = 'to'): array
    {
        $data = [];
        for ($i = 0; $i < $limit; $i++) {
            $number     = '88017'.$i.time();
            $number     = substr($number, 0, 13);
            $created_at = $factory->dateTimeBetween('-30 days');

            $data[] = [
                    'uid'               => uniqid(),
                    'user_id'           => 3,
                    'from'              => 'Codeglen',
                    'to'                => $number,
                    'message'           => $factory->text(120),
                    'sms_type'          => $smsType,
                    'status'            => $status,
                    'send_by'           => $sendBy,
                    'cost'              => '1',
                    'sms_count'         => 1,
                    'sending_server_id' => 183,
                    'created_at'        => $created_at,
                    'updated_at'        => $created_at,
            ];
        }

        return $data;
    }
}
