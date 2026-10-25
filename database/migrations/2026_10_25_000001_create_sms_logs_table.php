<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateSmsLogsTable
 *
 * Migration for SMS delivery audit logs, tracking direct sends, template notifications,
 * recipient details, delivery status, and provider responses.
 */
class CreateSmsLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('sms_logs')) {
            Schema::create('sms_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('recipient', 30)->index();
                $table->text('message');
                $table->string('template_key', 60)->nullable()->index();
                $table->string('provider', 50)->default('elitbuzz');
                $table->string('status', 20)->default('queued')->index(); // queued, sent, failed
                $table->text('response_payload')->nullable();
                $table->text('error_message')->nullable();
                $table->unsignedBigInteger('sent_by')->nullable()->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->unsignedBigInteger('student_id')->nullable()->index();
                $table->timestamps();

                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_logs');
    }
}
