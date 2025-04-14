<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('shop_id')->nullable();
            $table->string('order_number')->nullable();
            $table->string('order_status')->nullable();
            $table->string('order_type')->default('table')->nullable();
            $table->string('order_total')->nullable();
            $table->string('additional_notes')->nullable();
            $table->string('address')->nullable();
            $table->string('user_phone')->nullable();
            $table->string('user_name')->nullable();
            $table->text('order_items')->nullable();
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
        Schema::dropIfExists('orders');
    }
}
