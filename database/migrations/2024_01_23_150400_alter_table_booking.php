<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableBooking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('room_id');
            $table->string('email')->nullable()->after('full_name');
            $table->string('telephone')->nullable()->after('email');
            $table->string('total_price')->nullable()->after('telephone');
            $table->string('image')->nullable()->after('total_price');
            $table->enum('status', ['Waiting For Payment Check', 'Paid'])->default('Waiting For Payment Check')->nullable()->after('image');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('full_name');
            $table->dropColumn('email');
            $table->dropColumn('telephone');
            $table->dropColumn('total_price');
            $table->dropColumn('image');
            $table->dropColumn('status');
        });
    }
}