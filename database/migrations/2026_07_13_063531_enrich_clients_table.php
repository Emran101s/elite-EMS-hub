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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
            $table->string('contact_name')->nullable()->after('organization');
            $table->string('website')->nullable()->after('phone');
            $table->text('notes')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'contact_name', 'website', 'notes']);
        });
    }
};
