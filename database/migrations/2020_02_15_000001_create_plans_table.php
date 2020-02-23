<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('stripe_id')->index();
            $table->string('nickname')->nullable();
            $table->integer('amount')->nullable();
            $table->string('currency');
            $table->string('interval');
            $table->unsignedInteger('interval_count');
            $table->integer('trial_period_days')->nullable();
            $table->string('billing_scheme');
            $table->json('tiers')->nullable();
            $table->string('tiers_mode')->nullable();
            $table->json('metadata')->nullable();
            $table->string('product_name');
            $table->string('product_description')->nullable();
            $table->string('product_unit_label')->nullable();
            $table->string('custom_price_description')->nullable();
            $table->softDeletes();

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
        Schema::dropIfExists('plans');
    }
}