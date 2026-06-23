<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('round_number');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('raw_answer');
            $table->string('sanitized_answer');
            $table->timestamp('submitted_at')->useCurrent();
            $table->unique(['room_id', 'round_number', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_submissions');
    }
};
