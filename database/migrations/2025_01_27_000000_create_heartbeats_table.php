<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('heartbeats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('monitor_id');
            $table->string('status'); // 'up', 'down', 'pending'
            $table->integer('response_time')->nullable(); // milliseconds
            $table->integer('status_code')->nullable(); // HTTP status code
            $table->text('error_message')->nullable();
            $table->json('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->string('check_method')->default('GET');
            $table->timestamp('checked_at');
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('monitor_id')->references('id')->on('monitors')->onDelete('cascade');
            
            // Indexes for better performance
            $table->index(['monitor_id', 'checked_at']);
            $table->index('status');
            $table->index('checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heartbeats');
    }
};