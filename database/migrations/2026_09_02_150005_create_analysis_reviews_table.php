<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision');
            $table->text('reason')->nullable();
            $table->json('corrections')->nullable();
            $table->timestamps();

            $table->index(['analysis_run_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_reviews');
    }
};
