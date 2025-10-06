<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('gantt_chart_events', function (Blueprint $table) {
            // イベントID
            $table->id('gantt_chart_event_id')->comment(__('yokakit.target_id', ['target' =>  __('yokakit.event')]));
            // 工程ID
            $table->unsignedBigInteger('process_id')->comment(__('yokakit.target_id', ['target' => __('yokakit.process')]));
            // ガントチャートID
            $table->unsignedBigInteger('gantt_chart_id')->comment(__('yokakit.target_id', ['target' => __('yokakit.gantt_chart')]));
            // 信号
            $table->boolean('signal')->comment(__('yokakit.signal'));
            // タイムスタンプ
            $table->timestamp('at', 3)->index()->default(DB::raw('CURRENT_TIMESTAMP'));
            // 外部キー
            $table->foreign('process_id')->references('process_id')->on('processes')->cascadeOnDelete();
            $table->foreign('gantt_chart_id')->references('gantt_chart_id')->on('gantt_charts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gantt_chart_events');
    }
};
