<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transit_hub_id')->nullable()->constrained('transit_hubs')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status', 30);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['parcel_id', 'sequence']);
            $table->index(['parcel_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_logs');
    }
};
