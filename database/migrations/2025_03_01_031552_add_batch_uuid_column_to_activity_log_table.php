<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBatchUuidColumnToActivityLogTable extends Migration
{
    public function up()
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
            $table->unsignedBigInteger('change_type_id')->nullable()->after('properties');

            $table->foreign('change_type_id')
                ->references('id')
                ->on('change_types')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
            $table->dropForeign(['change_type_id']);
            $table->dropColumn('change_type_id');
        });
    }
}
