<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organisation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('user_id')->constrained('users');
            $table->string('role')->default('member');
            $table->timestamps();

            // Ensure a user can only have one role in an organisation
            $table->unique(['organisation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_user');
    }
};
