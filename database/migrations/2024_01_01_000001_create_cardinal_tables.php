<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardinal_issues', function (Blueprint $table) {
            $table->id();

            // Issue classification
            $table->string('type', 32)->index();           // slow | n_plus_one | missing_index
            $table->char('fingerprint', 40)->index();      // sha1 hex
            $table->text('template');                      // normalised SQL

            // Execution context
            $table->string('context_type', 32)->default('unknown'); // http|job|console|unknown
            $table->string('context_name')->default('unknown');

            // Timing / frequency
            $table->float('max_ms')->default(0);
            $table->float('total_ms')->default(0);
            $table->unsignedInteger('count')->default(1);

            // Detector-specific extras (EXPLAIN output, location, etc.)
            $table->json('payload')->nullable();

            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            // Composite: quickly find the worst offender by fingerprint
            $table->index(['fingerprint', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardinal_issues');
    }
};
