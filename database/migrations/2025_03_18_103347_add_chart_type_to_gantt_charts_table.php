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
        Schema::table('gantt_charts', function (Blueprint $table) {
            $table->tinyInteger('chart_type')->default(1)->after('chart_color')->comment(__('yokakit.gantt_chart_type'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gantt_charts', function (Blueprint $table) {
            $table->dropColumn('chart_type');
        });
    }
};
