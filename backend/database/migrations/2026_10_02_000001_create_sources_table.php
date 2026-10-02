<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();              // "Pakistan Penal Code, 1860"
            $table->string('short_name', 50)->nullable();   // "PPC"
            $table->string('unit', 20)->default('section'); // how provisions are numbered: section | article
            $table->smallInteger('year')->nullable();
            $table->string('source_url')->nullable();
            $table->string('file_name');
            $table->char('checksum', 64);                   // sha256 of the ingested file
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
