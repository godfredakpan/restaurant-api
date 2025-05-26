<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PromoCampaigns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promo_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->enum('type', ['percentage', 'fixed', 'bogo']);
            $table->decimal('discount_value', 8, 2);
            $table->text('description')->nullable();
            $table->json('valid_days')->nullable(); // Array of days (0-6)
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('show_on_menu')->default(true);
            $table->boolean('show_as_banner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('promo_code')->nullable()->unique();
            $table->integer('usage_limit')->nullable();
            $table->integer('times_used')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('promo_campaigns');
    }
}
