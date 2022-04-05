<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('market_prices', function (Blueprint $table) {
            $table->float('bid_price_diff_pst')->nullable()->after('ask_price');
            $table->float('ask_price_diff_pst')->nullable()->after('bid_price_diff_pst');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('market_prices', function (Blueprint $table) {
            $table->dropColumn('bid_price_diff_pst');
        });
        Schema::table('market_prices', function (Blueprint $table) {
            $table->dropColumn('ask_price_diff_pst');
        });
    }
};
