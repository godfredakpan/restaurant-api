<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReferredBusinessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referred_businesses', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->nullable();
            $table->string('name');
            $table->string('address');
            $table->string('phone')->nullable();
            $table->string('category');
            $table->string('referrer_name');
            $table->string('referrer_email');
            $table->string('referrer_phone');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referred_businesses');
    }
}
