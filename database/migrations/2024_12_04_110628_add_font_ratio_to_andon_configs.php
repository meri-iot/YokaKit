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
            $table->double('font_ratio', unsigned: true)->default(1.0)->after('fade')->comment(__('yokakit.font_ratio'));
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
            $table->dropColumn('font_ratio');
        });
    }
};
