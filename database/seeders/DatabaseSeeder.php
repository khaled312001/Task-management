<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $khaledId = DB::table('users')->insertGetId([
            'username' => 'khaled', 'email' => 'khaled@barmagly.tech',
            'password' => Hash::make('123456'), 'full_name' => 'خالد',
            'role' => 'developer', 'avatar_color' => '#6366f1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $muntherId = DB::table('users')->insertGetId([
            'username' => 'munther', 'email' => 'munther@barmagly.tech',
            'password' => Hash::make('123456'), 'full_name' => 'منذر',
            'role' => 'manager', 'avatar_color' => '#f59e0b',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Boards
        $board1 = DB::table('boards')->insertGetId([
            'name' => 'تطوير المواقع', 'description' => 'مشاريع تطوير وبرمجة المواقع',
            'category' => 'development', 'color' => '#6366f1', 'owner_id' => $khaledId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $board2 = DB::table('boards')->insertGetId([
            'name' => 'التسويق الرقمي', 'description' => 'مهام التسويق والحملات الإعلانية',
            'category' => 'marketing', 'color' => '#10b981', 'owner_id' => $muntherId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $board3 = DB::table('boards')->insertGetId([
            'name' => 'مشاريع العملاء', 'description' => 'المشاريع الحالية للعملاء',
            'category' => 'development', 'color' => '#f59e0b', 'owner_id' => $khaledId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([$board1, $board2, $board3] as $bid) {
            DB::table('board_members')->insert([
                ['board_id' => $bid, 'user_id' => $khaledId, 'role' => $bid == $board2 ? 'editor' : 'owner', 'created_at' => now(), 'updated_at' => now()],
                ['board_id' => $bid, 'user_id' => $muntherId, 'role' => $bid == $board2 ? 'owner' : 'editor', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $devLists = ['قيد الانتظار', 'قيد التنفيذ', 'مراجعة', 'مكتمل'];
        $mktLists = ['أفكار', 'قيد التنفيذ', 'مراجعة', 'منشور'];

        $listIds1 = [];
        foreach ($devLists as $i => $name) {
            $listIds1[] = DB::table('board_lists')->insertGetId([
                'board_id' => $board1, 'name' => $name, 'position' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $listIds2 = [];
        foreach ($mktLists as $i => $name) {
            $listIds2[] = DB::table('board_lists')->insertGetId([
                'board_id' => $board2, 'name' => $name, 'position' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ($devLists as $i => $name) {
            DB::table('board_lists')->insertGetId([
                'board_id' => $board3, 'name' => $name, 'position' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $now = now();
        DB::table('tasks')->insert([
            ['list_id' => $listIds1[0], 'board_id' => $board1, 'title' => 'تصميم الصفحة الرئيسية', 'description' => 'تصميم وتطوير الصفحة الرئيسية للموقع الجديد', 'priority' => 'high', 'status' => 'pending', 'assigned_to' => $khaledId, 'created_by' => $muntherId, 'due_date' => '2026-04-25', 'position' => 0, 'estimated_hours' => null, 'actual_hours' => null, 'is_archived' => false, 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['list_id' => $listIds1[0], 'board_id' => $board1, 'title' => 'إعداد قاعدة البيانات', 'description' => 'تصميم وإنشاء جداول قاعدة البيانات', 'priority' => 'urgent', 'status' => 'pending', 'assigned_to' => $khaledId, 'created_by' => $muntherId, 'due_date' => '2026-04-20', 'position' => 1, 'estimated_hours' => null, 'actual_hours' => null, 'is_archived' => false, 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['list_id' => $listIds1[1], 'board_id' => $board1, 'title' => 'تطوير API المستخدمين', 'description' => 'برمجة واجهة API للمستخدمين', 'priority' => 'medium', 'status' => 'in_progress', 'assigned_to' => $khaledId, 'created_by' => $muntherId, 'due_date' => '2026-04-28', 'position' => 0, 'estimated_hours' => null, 'actual_hours' => null, 'is_archived' => false, 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['list_id' => $listIds1[2], 'board_id' => $board1, 'title' => 'مراجعة كود الدفع', 'description' => 'مراجعة كود نظام الدفع والتأكد من الأمان', 'priority' => 'high', 'status' => 'review', 'assigned_to' => $muntherId, 'created_by' => $khaledId, 'due_date' => '2026-04-22', 'position' => 0, 'estimated_hours' => null, 'actual_hours' => null, 'is_archived' => false, 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['list_id' => $listIds1[3], 'board_id' => $board1, 'title' => 'إعداد بيئة التطوير', 'description' => 'تجهيز بيئة التطوير والسيرفر', 'priority' => 'low', 'status' => 'completed', 'assigned_to' => $khaledId, 'created_by' => $khaledId, 'due_date' => null, 'position' => 0, 'estimated_hours' => null, 'actual_hours' => null, 'is_archived' => false, 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('tasks')->insert([
            ['list_id' => $listIds2[0], 'board_id' => $board2, 'title' => 'حملة إعلانية على فيسبوك', 'description' => 'إعداد حملة إعلانية جديدة', 'priority' => 'high', 'status' => 'pending', 'assigned_to' => $muntherId, 'created_by' => $muntherId, 'due_date' => '2026-04-22', 'position' => 0, 'estimated_hours' => null, 'actual_hours' => null, 'is_archived' => false, 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['list_id' => $listIds2[1], 'board_id' => $board2, 'title' => 'كتابة محتوى المدونة', 'description' => 'كتابة 5 مقالات للمدونة', 'priority' => 'medium', 'status' => 'in_progress', 'assigned_to' => $muntherId, 'created_by' => $muntherId, 'due_date' => '2026-04-30', 'position' => 0, 'estimated_hours' => null, 'actual_hours' => null, 'is_archived' => false, 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now],
            ['list_id' => $listIds2[0], 'board_id' => $board2, 'title' => 'تحسين SEO للموقع', 'description' => 'تحسين محركات البحث للصفحات الرئيسية', 'priority' => 'medium', 'status' => 'pending', 'assigned_to' => $khaledId, 'created_by' => $muntherId, 'due_date' => '2026-05-05', 'position' => 1, 'estimated_hours' => null, 'actual_hours' => null, 'is_archived' => false, 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('labels')->insert([
            ['board_id' => $board1, 'name' => 'باگ', 'color' => '#ef4444', 'created_at' => $now, 'updated_at' => $now],
            ['board_id' => $board1, 'name' => 'ميزة جديدة', 'color' => '#10b981', 'created_at' => $now, 'updated_at' => $now],
            ['board_id' => $board1, 'name' => 'تحسين', 'color' => '#6366f1', 'created_at' => $now, 'updated_at' => $now],
            ['board_id' => $board1, 'name' => 'عاجل', 'color' => '#f59e0b', 'created_at' => $now, 'updated_at' => $now],
            ['board_id' => $board2, 'name' => 'سوشيال ميديا', 'color' => '#3b82f6', 'created_at' => $now, 'updated_at' => $now],
            ['board_id' => $board2, 'name' => 'SEO', 'color' => '#8b5cf6', 'created_at' => $now, 'updated_at' => $now],
            ['board_id' => $board2, 'name' => 'محتوى', 'color' => '#ec4899', 'created_at' => $now, 'updated_at' => $now],
            ['board_id' => $board2, 'name' => 'إعلانات', 'color' => '#f97316', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
