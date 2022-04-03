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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->integer('firi_id')->index()->unique()->unsigned()->nullable();
            $table->string('market',6)->nullable();
            $table->string('type',3)->nullable();
            $table->float('price')->nullable();
            $table->float('amount')->nullable();
            $table->integer('firi_sale_id')->unsigned()->nullable();
            $table->float('sale_amount')->nullable();
            $table->float('earned_amount')->nullable();
            $table->string('status',6)->nullable()->default("open");
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
};
