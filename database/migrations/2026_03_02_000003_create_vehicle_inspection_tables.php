<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_inspection_templates')) {
            Schema::create('vehicle_inspection_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                $table->string('name', 160);
                $table->string('vehicle_type', 80)->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'active'], 'veh_insp_tpl_company_active_idx');
            });
        }

        if (! Schema::hasTable('vehicle_inspection_template_categories')) {
            Schema::create('vehicle_inspection_template_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('vehicle_inspection_templates')->cascadeOnDelete();
                $table->string('name', 160);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['template_id', 'sort_order'], 'veh_insp_tpl_cat_order_idx');
            });
        }

        if (! Schema::hasTable('vehicle_inspection_template_items')) {
            Schema::create('vehicle_inspection_template_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('vehicle_inspection_template_categories')->cascadeOnDelete();
                $table->string('label', 180);
                $table->boolean('required')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['category_id', 'sort_order'], 'veh_insp_tpl_item_order_idx');
            });
        }

        if (! Schema::hasTable('vehicle_inspections')) {
            Schema::create('vehicle_inspections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('vehicle_inspection_templates')->nullOnDelete();
                $table->dateTime('inspected_at');
                $table->unsignedInteger('odometer')->nullable();
                $table->string('driver_name', 160)->nullable();
                $table->string('supervisor_name', 160)->nullable();
                $table->decimal('compliance_percent', 5, 2)->default(0);
                $table->enum('status', ['approved', 'attention_required', 'rejected'])->default('approved');
                $table->text('observations')->nullable();
                $table->longText('driver_signature')->nullable();
                $table->longText('supervisor_signature')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'vehicle_id', 'inspected_at'], 'veh_insp_company_vehicle_date_idx');
                $table->index(['vehicle_id', 'status'], 'veh_insp_vehicle_status_idx');
            });
        }

        if (! Schema::hasTable('vehicle_inspection_results')) {
            Schema::create('vehicle_inspection_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inspection_id')->constrained('vehicle_inspections')->cascadeOnDelete();
                $table->foreignId('template_item_id')->nullable()->constrained('vehicle_inspection_template_items')->nullOnDelete();
                $table->string('category_name', 160);
                $table->string('item_label', 180);
                $table->boolean('passed')->default(true);
                $table->text('notes')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['inspection_id', 'passed'], 'veh_insp_result_passed_idx');
            });
        }

        if (! Schema::hasTable('vehicle_inspection_attachments')) {
            Schema::create('vehicle_inspection_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inspection_id')->constrained('vehicle_inspections')->cascadeOnDelete();
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_inspection_attachments');
        Schema::dropIfExists('vehicle_inspection_results');
        Schema::dropIfExists('vehicle_inspections');
        Schema::dropIfExists('vehicle_inspection_template_items');
        Schema::dropIfExists('vehicle_inspection_template_categories');
        Schema::dropIfExists('vehicle_inspection_templates');
    }
};
