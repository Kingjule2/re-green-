<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('land_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parcel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('data_status')->default('demo');
            $table->string('status')->default('uploaded');
            $table->json('provenance')->nullable();
            $table->json('vertical_metadata')->nullable();
            $table->timestamps();

            $table->index(['land_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
