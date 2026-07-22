<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_players', function (Blueprint $table) {
            $table->unsignedInteger('times_fooled')->default(0)->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('room_players', function (Blueprint $table) {
            $table->dropColumn('times_fooled');
        });
    }
};
