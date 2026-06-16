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
        Schema::table('andon_configs', function (Blueprint $table) {
            // 目標値を表示するかどうか
            $table->boolean('is_show_goal')->default(false)->after('is_show_overall_equipment_effectiveness')->comment(__('yokakit.goal'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('andon_configs', function (Blueprint $table) {
            // 目標値の削除
            $table->dropColumn('is_show_goal');
        });
    }
};
