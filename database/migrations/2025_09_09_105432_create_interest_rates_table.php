<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('interest_rates', function (Blueprint $table) {
        $table->id();
        $table->string('type'); // e.g. "loan", "fd", "sip"
        $table->decimal('rate', 5, 2); // e.g. 8.50
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interest_rates');
    }
};
