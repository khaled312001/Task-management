<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SMTP Settings per user
        Schema::create('smtp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->default('Default');
            $table->string('host', 255);
            $table->integer('port')->default(587);
            $table->enum('encryption', ['tls', 'ssl', 'none'])->default('tls');
            $table->string('username', 255);
            $table->text('password');
            $table->string('from_email', 255);
            $table->string('from_name', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Contact lists
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->integer('contacts_count')->default(0);
            $table->timestamps();
        });

        // Contacts
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('contact_lists')->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('name', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->json('extra_data')->nullable();
            $table->boolean('is_subscribed')->default(true);
            $table->timestamps();
            $table->index(['list_id', 'email']);
        });

        // Email campaigns
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('smtp_setting_id')->nullable()->constrained('smtp_settings')->nullOnDelete();
            $table->foreignId('list_id')->nullable()->constrained('contact_lists')->nullOnDelete();
            $table->string('name', 255);
            $table->string('subject', 255);
            $table->longText('html_content');
            $table->enum('status', ['draft', 'sending', 'sent', 'paused', 'failed'])->default('draft');
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('delay_seconds')->default(2);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // Email send log
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('email', 255);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // WhatsApp sessions
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_name', 100)->default('default');
            $table->enum('status', ['disconnected', 'qr_pending', 'connected'])->default('disconnected');
            $table->string('phone_number', 50)->nullable();
            $table->text('qr_code')->nullable();
            $table->timestamps();
        });

        // WhatsApp campaigns
        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('whatsapp_sessions')->nullOnDelete();
            $table->string('name', 255);
            $table->longText('message');
            $table->string('media_path', 500)->nullable();
            $table->enum('status', ['draft', 'sending', 'sent', 'paused', 'failed'])->default('draft');
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('delay_seconds')->default(5);
            $table->timestamps();
        });

        // WhatsApp contacts (separate from email contacts)
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();
            $table->string('phone', 50);
            $table->string('name', 255)->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_sessions');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_lists');
        Schema::dropIfExists('smtp_settings');
    }
};
