<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('position');
            $table->string('color',20)->nullable();
            $table->integer('wip_limit')->nullable();
            $table->foreignId('board_id')->constrained('boards')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_columns');
    }
};
