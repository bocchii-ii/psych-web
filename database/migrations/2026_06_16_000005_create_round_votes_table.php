<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('round_number');
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            // null means the player voted for the correct answer (not a submission)
            $table->foreignId('submission_id')->nullable()->constrained('round_submissions')->nullOnDelete();
            $table->timestamp('voted_at')->useCurrent();
            $table->unique(['room_id', 'round_number', 'voter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_votes');
    }
};
