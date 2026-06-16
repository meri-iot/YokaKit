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
        Schema::create('gantt_charts', function (Blueprint $table) {
            // ID
            $table->id('gantt_chart_id')->comment(__('yokakit.target_id', ['target' =>  __('yokakit.gantt_chart')]));
            // 工程ID
            $table->unsignedBigInteger('process_id')->comment(__('yokakit.target_id', ['target' => __('yokakit.process')]));
            // ラズパイID
            $table->unsignedBigInteger('raspberry_pi_id')->comment(__('yokakit.target_id', ['target' => __('yokakit.raspberry_pi')]));
            // ピン番号
            $table->tinyInteger('pin_number', false, true)->comment(__('yokakit.pin_number'));
            // チャート名
            $table->string('chart_name', 32)->comment(__('yokakit.target_name', ['target' => __('yokakit.chart_name')]));
            // チャートカラー
            $table->char('chart_color', 7)->comment(__('yokakit.chart_color'));
            // トリガー
            $table->boolean('trigger')->comment(__('yokakit.trigger'));
            // 信号
            $table->boolean('signal')->nullable()->comment(__('yokakit.signal'));
            // 順序
            $table->integer('order')->default(2147483647)->comment(__('yokakit.order'));
            // タイムスタンプ
            $table->timestamps();
            // 外部キー
            $table->foreign('process_id')->references('process_id')->on('processes')->cascadeOnDelete();
            $table->foreign('raspberry_pi_id')->references('raspberry_pi_id')->on('raspberry_pis')->cascadeOnDelete();
            // 複合ユニーク
            $table->unique(['process_id', 'chart_name']);
            $table->unique(['raspberry_pi_id', 'pin_number']);
            // 複合インデックス
            $table->index(['raspberry_pi_id', 'pin_number']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gantt_charts');
    }
};
