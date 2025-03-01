<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('status_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->foreignId('from_status_id')->constrained('statuses');
            $table->foreignId('to_status_id')->constrained('statuses');
            $table->foreignId('board_id')->constrained('boards');
            $table->timestamps();

            // Each transition from one status to another should be unique per board
            $table->unique(['from_status_id', 'to_status_id', 'board_id'], 'unique_transition_per_board');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_transitions');
    }
};
