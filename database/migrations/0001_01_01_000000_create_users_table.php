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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('รหัสหลัก (UUID)');
            $table->string('username', 30)->unique()->comment('ชื่อผู้ใช้ (ไม่ซ้ำกัน)');
            $table->string('email', 191)->unique()->comment('อีเมล (ไม่ซ้ำกัน)');
            $table->string('name');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('name_th', 100)->nullable()->comment('ชื่อแสดงผล (ภาษาไทย)');
            $table->string('name_en', 100)->nullable()->comment('ชื่อแสดงผล (ภาษาอังกฤษ)');
            $table->string('nickname_th', 50)->nullable()->comment('ชื่อเล่น (ภาษาไทย)');
            $table->string('nickname_en', 50)->nullable()->comment('ชื่อเล่น (ภาษาอังกฤษ)');
            // Audit fields
            $table->uuid('guide_by')->nullable()->index()->comment('ผู้แนะนำ (FK → users.id)');
            $table->uuid('created_by')->nullable()->index()->comment('ผู้สร้างบันทึก (FK → users.id)');
            $table->uuid('updated_by')->nullable()->index()->comment('ผู้แก้ไขล่าสุด (FK → users.id)');

            // ข้อมูลเพิ่มเติม
            $table->json('metadata')->nullable()->comment('ข้อมูลเพิ่มเติมในรูปแบบ JSON');

            // สถิติและค่าเฉพาะผู้ใช้
            $table->unsignedBigInteger('count_in')->default(0)->comment('จำนวนครั้งที่เข้าสู่ระบบ');
            $table->decimal('score', 12, 4)->default(0)->comment('คะแนน (ใช้ decimal เพื่อความแม่นยำ)');
            $table->decimal('coin', 12, 4)->default(0)->comment('เหรียญ (ใช้ decimal เพื่อความแม่นยำ)');
            // สถานะ
            $table->boolean('is_active')->default(true)->index()->comment('สถานะการใช้งาน');
            $table->timestamp('expires_at')->nullable()->comment('วันหมดอายุบัญชี');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes()->index()->comment('วันที่ลบ (Soft Delete)');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            //  $table->foreignId('user_id')->nullable()->index();
            $table->foreignUuid('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate()
                ->comment('รหัสผู้ใช้ (FK → users.id)');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
