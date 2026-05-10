<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<SQL
            CREATE VIEW player_sprint_stats AS
            SELECT
                p.id                        AS player_id,
                p.name                      AS player_name,
                p.birthdate,
                ms.sprint_10m          AS sprint_10m,
                ms.sprint_20m          AS sprint_20m,
                ms.sprint_30m          AS sprint_30m,
                ms.date                AS measurement_date
            FROM measure_sprints ms 
            LEFT JOIN players p ON p.id = ms.player_id
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS player_sprint_stats');
    }
};
