<?php
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            [
                'id' => 1,
                'username' => 'admin999',
                'kana_first_name' => 'カンリ',
                'kana_last_name' => 'カンリ',
                'first_name' => 'admin',
                'last_name' => 'admin',
                'email' => 'admin@admin.com',
                'password' => bcrypt('12345678'),
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
                'role_type' => 1,
                'phone' => '3336669999',
                'company_code' => 'sa5th4frs2g2',
                'comment' => 'This is account admin',
            ]
        ]);
    }
}
