<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('role_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->integer('level')->default(1); // Hierarchy level (higher = more access)
            $table->boolean('is_system')->default(false); // System templates cannot be deleted
            $table->unsignedBigInteger('organisation_id')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'is_system']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('role_templates');
    }
};
