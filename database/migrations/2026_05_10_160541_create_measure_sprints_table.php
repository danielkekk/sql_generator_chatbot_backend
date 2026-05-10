<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('measure_sprints', function (Blueprint $table) {
            $table->increments('measure_sprint_id');
            $table->unsignedInteger('player_id');
            $table->double('sprint_10m');
            $table->double('sprint_20m');
            $table->double('sprint_30m');
            $table->date('date');

            $table->foreign('player_id')
                  ->references('id')
                  ->on('players');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('measure_sprints');
    }
};
