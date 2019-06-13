<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('role_id');
            $table->integer('company_id');
            $table->string('username', 30);
            $table->string('password', 60);
            $table->string('first_name', 20);
            $table->string('last_name', 20);
            $table->string('kana_first_name', 20);
            $table->string('kana_last_name',20);
            $table->string('image_path',200);
            $table->string('address', 100);
            $table->string('email');
            $table->string('phone', 12);
            $table->text('comment')->nullable();
            $table->boolean('is_enable')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::table('users', function(Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('set null')
                ->onUpdate('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function(Blueprint $table) {
            $table->dropForeign('users_company_id_foreign');
        });

        Schema::drop('users');
    }
}
