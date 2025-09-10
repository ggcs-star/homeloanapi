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
    Schema::create('basic_loans', function (Blueprint $table) {
        $table->id();
        $table->string('borrower_name');
        $table->decimal('principal_amount', 15, 2);
        $table->decimal('interest_rate', 5, 2);
        $table->integer('term_years');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('basic_loans');
    }
};
