<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->dateTime('held_at');
            // ISO-639-1 code passed to transcription, null = auto-detect
            $table->string('language', 5)->nullable();
            $table->string('status', 20)->default('uploaded');
            $table->text('error')->nullable();

            $table->string('audio_path')->nullable();
            $table->string('audio_mime')->nullable();
            $table->unsignedBigInteger('audio_size')->nullable();
            $table->timestamp('audio_deleted_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // [{speaker, start, end, text}, ...]
            $table->json('transcript')->nullable();
            // {"speaker_0": "Peter", ...}
            $table->json('speakers')->nullable();

            $table->longText('summary')->nullable();
            $table->json('decisions')->nullable();
            $table->json('open_questions')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'held_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
