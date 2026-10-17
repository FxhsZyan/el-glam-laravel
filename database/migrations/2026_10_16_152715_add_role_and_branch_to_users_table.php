<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
       public function up(): void
   {
       Schema::table('users', function (Blueprint $table) {
           $table->string('role')->default('staff')->after('password');
           $table->foreignId('branch_id')->nullable()->after('role')
                 ->constrained('branches')->nullOnDelete();
       });
   }

   public function down(): void
   {
       Schema::table('users', function (Blueprint $table) {
           $table->dropConstrainedForeignId('branch_id');
           $table->dropColumn('role');
       });
   }
};
