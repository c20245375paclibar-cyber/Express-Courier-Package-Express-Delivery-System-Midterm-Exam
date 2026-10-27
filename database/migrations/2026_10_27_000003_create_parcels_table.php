<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number', 50)->unique();
            $table->foreignId('sender_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('recipient_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('current_hub_id')->nullable()->constrained('transit_hubs')->nullOnDelete();
            $table->decimal('parcel_weight_kg', 8, 3);
            $table->string('delivery_type', 20);
            $table->decimal('shipping_cost', 10, 2);
            $table->timestamps();

            $table->index(['sender_customer_id', 'recipient_customer_id']);
            $table->index(['current_hub_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
