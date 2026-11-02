<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('digest_enabled')->default(true);
            // Local time (app timezone) after which the daily e-mail goes out
            $table->string('digest_time', 5)->default('07:00');
            $table->boolean('digest_weekends')->default(false);
            $table->date('digest_last_sent_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['digest_enabled', 'digest_time', 'digest_weekends', 'digest_last_sent_on']);
        });
    }
};
