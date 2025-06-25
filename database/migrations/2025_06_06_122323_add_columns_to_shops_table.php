<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToShopsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::table('shops', function (Blueprint $table) {
            $table->string('primary_color', 7)->nullable()->after('banner');
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
            $table->string('card_background', 7)->nullable()->after('secondary_color');
            $table->string('account_bank_code', 10)->nullable()->after('account_bank');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color', 'card_background']);
        });
    }
}
