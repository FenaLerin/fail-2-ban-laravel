<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreateFail2banTable extends Migration
{
    use softDeletes;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fail2ban', function (Blueprint $table) {
            $table->increments('id');
            $table->text('access_ip');
            $table->text('access_url');
            $table->unsignedInteger('ban_level');
            $table->dateTime('unban_date');
            $table->dateTime('clean_date');
            $table->timestamps();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fail2ban');
    }
}
