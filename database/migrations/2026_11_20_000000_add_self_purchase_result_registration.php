<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('name_key', 64)->unique();
            $table->string('contact_name', 100);
            $table->string('contact_phone', 30);
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('procurement_executions', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('procurement_suppliers');
            $table->string('supplier_contact_name', 100)->nullable();
            $table->string('supplier_contact_phone', 30)->nullable();
        });

        $rules = DB::table('procurement_archive_rules')->where('application_form', 'self_purchase');
        $required = (clone $rules)->where('required', true)->orderBy('kind')->pluck('kind')->all();
        $customized = (clone $rules)->whereNotNull('updated_by')->exists()
            || $required !== ['acceptance', 'contract', 'invoice', 'result_announcement'];

        if (! $customized) {
            (clone $rules)->whereIn('kind', ['acceptance', 'invoice', 'result_announcement'])
                ->update(['required' => false, 'updated_at' => now()]);
        }

        DB::table('procurement_archive_rules')->insert([
            'application_form' => 'self_purchase',
            'kind' => 'purchase_process',
            'required' => ! $customized,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $rules = DB::table('procurement_archive_rules')->where('application_form', 'self_purchase');
        if (! (clone $rules)->whereNotNull('updated_by')->exists()
            && (clone $rules)->where('required', true)->orderBy('kind')->pluck('kind')->all() === ['contract', 'purchase_process']) {
            (clone $rules)->whereIn('kind', ['acceptance', 'invoice', 'result_announcement'])
                ->update(['required' => true, 'updated_at' => now()]);
        }
        (clone $rules)->where('kind', 'purchase_process')->delete();

        Schema::table('procurement_executions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['supplier_contact_name', 'supplier_contact_phone']);
        });
        Schema::dropIfExists('procurement_suppliers');
    }
};
