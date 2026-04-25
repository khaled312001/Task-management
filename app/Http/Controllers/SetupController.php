<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class SetupController extends Controller
{
    public static function addNewProjects()
    {
        $khaledId = DB::table('users')->where('username', 'khaled')->value('id');
        $muntherId = DB::table('users')->where('username', 'munther')->value('id');

        if (!$khaledId || !$muntherId) {
            return response()->json(['error' => 'Users khaled and munther not found'], 404);
        }

        $now = now();
        $projects = self::getProjectsData();
        $createdCount = 0;
        $tasksCount = 0;
        $skipped = [];
        $created = [];

        foreach ($projects as $proj) {
            $existing = DB::table('boards')->where('name', $proj['name'])->first();
            if ($existing) {
                $skipped[] = $proj['name'];
                continue;
            }

            $boardId = DB::table('boards')->insertGetId([
                'name' => $proj['name'],
                'description' => $proj['description'],
                'category' => $proj['category'],
                'color' => $proj['color'],
                'owner_id' => $khaledId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('board_members')->insert([
                ['board_id' => $boardId, 'user_id' => $khaledId, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now],
                ['board_id' => $boardId, 'user_id' => $muntherId, 'role' => 'editor', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $listIds = [];
            foreach ($proj['lists'] as $i => $listName) {
                $listIds[] = DB::table('board_lists')->insertGetId([
                    'board_id' => $boardId, 'name' => $listName, 'position' => $i,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $position = [0, 0, 0, 0];
            foreach ($proj['tasks'] as $task) {
                $assigneeId = $task['assignee'] === 'khaled' ? $khaledId : $muntherId;
                DB::table('tasks')->insert([
                    'list_id' => $listIds[$task['list']],
                    'board_id' => $boardId,
                    'title' => $task['title'],
                    'description' => $task['desc'],
                    'priority' => $task['priority'],
                    'status' => 'pending',
                    'assigned_to' => $assigneeId,
                    'created_by' => $muntherId,
                    'position' => $position[$task['list']]++,
                    'is_archived' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $tasksCount++;
            }

            $labels = [
                ['name' => 'باگ', 'color' => '#ef4444'],
                ['name' => 'ميزة جديدة', 'color' => '#10b981'],
                ['name' => 'تسويق', 'color' => '#8b5cf6'],
                ['name' => 'إعلانات', 'color' => '#f59e0b'],
                ['name' => 'عاجل', 'color' => '#dc2626'],
            ];
            foreach ($labels as $label) {
                DB::table('labels')->insert([
                    'board_id' => $boardId, 'name' => $label['name'], 'color' => $label['color'],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $created[] = $proj['name'] . ' (' . count($proj['tasks']) . ' tasks)';
            $createdCount++;
        }

        return response()->json([
            'success' => true,
            'created_projects' => $createdCount,
            'total_tasks_added' => $tasksCount,
            'created' => $created,
            'skipped' => $skipped,
            'message' => "تم إضافة $createdCount مشروع جديد بـ $tasksCount مهمة",
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private static function getProjectsData(): array
    {
        return [
            [
                'name' => 'POS للمطاعم',
                'description' => 'نظام نقاط البيع المتكامل للمطاعم والكافيهات',
                'category' => 'development',
                'color' => '#ef4444',
                'lists' => ['قيد الانتظار', 'قيد التنفيذ', 'مراجعة', 'مكتمل'],
                'tasks' => [
                    ['title' => 'إصلاح بطء عرض الفواتير', 'desc' => 'الفواتير تأخذ أكثر من 5 ثواني للعرض في ساعات الذروة', 'priority' => 'urgent', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'إصلاح حساب الضريبة (VAT)', 'desc' => 'مشكلة في حساب الضريبة عند تطبيق الخصومات', 'priority' => 'high', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'إصلاح طباعة الفواتير العربية', 'desc' => 'الأرقام والنصوص العربية مقلوبة في الطباعة الحرارية', 'priority' => 'high', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'ربط نظام الدفع الإلكتروني (Mada/Apple Pay)', 'desc' => 'دمج بوابات الدفع السعودية', 'priority' => 'high', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'إضافة نظام نقاط الولاء للعملاء', 'desc' => 'برنامج Loyalty Points لتشجيع العملاء على العودة', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'تطوير تطبيق موبايل للنادل', 'desc' => 'تطبيق Flutter لاستقبال الطلبات على الطاولات', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'نظام إدارة المخزون', 'desc' => 'تتبع المواد الخام وإنذارات النقص التلقائية', 'priority' => 'medium', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'تقارير المبيعات المتقدمة', 'desc' => 'لوحة تحكم تحليلية للمبيعات اليومية والشهرية', 'priority' => 'medium', 'list' => 2, 'assignee' => 'khaled'],
                    ['title' => 'تصميم بنرات ترويجية للمطاعم', 'desc' => 'بنرات بأحجام مختلفة لمنصات التواصل', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'إنشاء فيديو تعريفي للنظام (3 دقائق)', 'desc' => 'فيديو موشن جرافيك يشرح مميزات النظام', 'priority' => 'high', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'كتابة 10 مقالات للمدونة', 'desc' => 'محتوى SEO لجذب أصحاب المطاعم', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'حملة بريد إلكتروني للمطاعم المستهدفة', 'desc' => 'استهداف 1000 مطعم بعرض تجريبي', 'priority' => 'high', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'إطلاق إعلان فيسبوك مستهدف', 'desc' => 'استهداف أصحاب المطاعم في الرياض وجدة', 'priority' => 'high', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'إعلانات Google Ads', 'desc' => 'كلمات مفتاحية: نظام كاشير، POS مطاعم', 'priority' => 'high', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'إعلانات إنستجرام مع Influencers', 'desc' => 'التعاقد مع 3 مؤثرين في مجال الطعام', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'تحسين SEO للموقع', 'desc' => 'تحسين الترتيب على Google للكلمات المفتاحية', 'priority' => 'medium', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'تدريب العملاء الحاليين على المميزات الجديدة', 'desc' => 'جلسات Zoom جماعية أسبوعية', 'priority' => 'low', 'list' => 0, 'assignee' => 'munther'],
                ],
            ],
            [
                'name' => 'نظام صالونات الحلاقة',
                'description' => 'نظام إدارة صالونات الحلاقة والباربر شوب',
                'category' => 'development',
                'color' => '#8b5cf6',
                'lists' => ['قيد الانتظار', 'قيد التنفيذ', 'مراجعة', 'مكتمل'],
                'tasks' => [
                    ['title' => 'إصلاح تقويم المواعيد على الموبايل', 'desc' => 'التقويم لا يعمل بشكل صحيح على iPhone', 'priority' => 'urgent', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'إصلاح عدم وصول إشعارات SMS', 'desc' => 'بعض العملاء لا يستلمون رسائل التذكير', 'priority' => 'high', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'إصلاح ظهور المواعيد المحجوزة كمتاحة', 'desc' => 'تضارب في عرض المواعيد عند الحجز المتزامن', 'priority' => 'urgent', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'حجز المواعيد عبر WhatsApp Bot', 'desc' => 'بوت ذكي يستقبل الحجوزات عبر واتساب', 'priority' => 'high', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'نظام تقييم الحلاقين', 'desc' => 'تقييم 5 نجوم بعد كل خدمة', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'لوحة تحكم لتتبع الإيرادات اليومية', 'desc' => 'إحصائيات مالية في الوقت الفعلي', 'priority' => 'medium', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'نظام عمولات الحلاقين', 'desc' => 'حساب تلقائي لعمولة كل حلاق', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'تطبيق موبايل للعملاء', 'desc' => 'تطبيق iOS/Android للحجز والمتابعة', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'تصميم بروشور تعريفي للنظام', 'desc' => 'بروشور احترافي بصيغ PDF و طباعة', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'محتوى سوشيال ميديا أسبوعي', 'desc' => '4 منشورات أسبوعية لمدة شهر', 'priority' => 'high', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'فيديو تجربة عميل', 'desc' => 'تصوير صالون عميل يستخدم النظام', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'حملة فيسبوك مستهدفة لأصحاب الصالونات', 'desc' => 'استهداف صفحات صالونات في السعودية والإمارات', 'priority' => 'high', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'إعلان TikTok بميزانية مفتوحة', 'desc' => 'الوصول لجيل Z من أصحاب الصالونات الجدد', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'حملة Snapchat للسوق السعودي', 'desc' => 'إعلان موجه للسوق السعودي', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'دعم فني للعملاء الحاليين', 'desc' => 'متابعة دورية لحل المشاكل', 'priority' => 'low', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'تدريب موظفين الصالونات على النظام', 'desc' => 'فيديوهات تدريبية مفصلة', 'priority' => 'low', 'list' => 0, 'assignee' => 'munther'],
                ],
            ],
            [
                'name' => 'موقع الأكاديمية',
                'description' => 'منصة الكورسات والتدريب الأونلاين',
                'category' => 'development',
                'color' => '#3b82f6',
                'lists' => ['قيد الانتظار', 'قيد التنفيذ', 'مراجعة', 'مكتمل'],
                'tasks' => [
                    ['title' => 'إصلاح مشكلة الفيديوهات على الموبايل', 'desc' => 'الفيديوهات لا تشتغل على متصفح Safari', 'priority' => 'urgent', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'إصلاح بطء تحميل صفحة الكورسات', 'desc' => 'الصفحة تأخذ أكثر من 6 ثواني للتحميل', 'priority' => 'high', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'إصلاح خطأ التسجيل بالبريد الإلكتروني', 'desc' => 'بعض الإيميلات لا تستقبل رابط التفعيل', 'priority' => 'high', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'نظام شهادات تلقائي', 'desc' => 'إصدار شهادات PDF تلقائياً عند إكمال الكورس', 'priority' => 'high', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'نظام اختبارات تفاعلي', 'desc' => 'اختبارات بأنواع مختلفة (MCQ, درج وإسقاط، إلخ)', 'priority' => 'high', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'تطوير لوحة تحكم الطالب', 'desc' => 'تتبع التقدم في الكورسات والإحصائيات', 'priority' => 'medium', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'ربط نظام الدفع (Stripe + PayPal)', 'desc' => 'دمج بوابات دفع دولية', 'priority' => 'high', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'نظام دردشة مباشر مع المدرس', 'desc' => 'Live Chat مع المدرسين أثناء الدروس', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'نظام البث المباشر للمحاضرات', 'desc' => 'دمج Zoom/Jitsi للمحاضرات الحية', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'محتوى ترويجي للكورسات', 'desc' => 'بنرات وفيديوهات لكل كورس', 'priority' => 'high', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'فيديوهات تعريفية للكورسات', 'desc' => 'Trailer لكل كورس بمدة دقيقتين', 'priority' => 'high', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'إطلاق مدونة تعليمية', 'desc' => 'مقالات SEO أسبوعية لجذب الطلاب', 'priority' => 'medium', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'حملة بريد إلكتروني للطلاب الحاليين', 'desc' => 'إعلان عن الكورسات الجديدة', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'إعلانات YouTube قبل فيديوهات تعليمية', 'desc' => 'استهداف المهتمين بالتعلم الإلكتروني', 'priority' => 'high', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'حملة Google Ads للكورسات الأكثر طلباً', 'desc' => 'كلمات مفتاحية: كورسات أونلاين، تعلم برمجة', 'priority' => 'high', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'إعلانات TikTok للجيل الجديد', 'desc' => 'محتوى قصير جذاب للطلاب الجامعيين', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'حملة Instagram Reels', 'desc' => 'مقاطع ريلز عن نصائح تعليمية', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'استطلاع رأي الطلاب الحاليين', 'desc' => 'فهم احتياجات الطلاب لتحسين الخدمة', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'برنامج إحالة (Referral Program)', 'desc' => 'الطالب يحصل على خصم عند جلب طالب جديد', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'متابعة الطلاب المنقطعين', 'desc' => 'تواصل مع الطلاب اللي توقفوا عن الدراسة', 'priority' => 'low', 'list' => 1, 'assignee' => 'munther'],
                ],
            ],
            [
                'name' => 'موقع أمان لو',
                'description' => 'منصة الاستشارات القانونية والخدمات للمحامين',
                'category' => 'development',
                'color' => '#10b981',
                'lists' => ['قيد الانتظار', 'قيد التنفيذ', 'مراجعة', 'مكتمل'],
                'tasks' => [
                    ['title' => 'إصلاح صفحة الاستشارات على الموبايل', 'desc' => 'النموذج لا يظهر بشكل صحيح على الموبايل', 'priority' => 'urgent', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'إصلاح خطأ رفع الملفات الكبيرة', 'desc' => 'الملفات الأكبر من 5MB لا تُرفع', 'priority' => 'high', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'إصلاح بحث المحامين بالتخصص', 'desc' => 'النتائج لا تظهر بشكل صحيح حسب التخصص', 'priority' => 'high', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'نظام حجز استشارات مع المحامين', 'desc' => 'حجز مواعيد استشارة فيديو/مكالمة/مكتب', 'priority' => 'urgent', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'لوحة تحكم متقدمة للمحامين', 'desc' => 'إدارة القضايا، العملاء، والمواعيد', 'priority' => 'high', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'نظام رفع وتنظيم ملفات القضايا', 'desc' => 'تخزين سحابي آمن لملفات القضايا', 'priority' => 'high', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'نظام دفع آمن للاستشارات', 'desc' => 'دمج بوابات دفع مع نظام escrow', 'priority' => 'high', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'محرر العقود الذكي', 'desc' => 'قوالب عقود جاهزة قابلة للتعديل', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'نظام مكالمات فيديو مدمج', 'desc' => 'مكالمات فيديو آمنة بين المحامي والعميل', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'نظام تقييم المحامين', 'desc' => 'تقييمات وريفيوز موثقة من العملاء', 'priority' => 'medium', 'list' => 1, 'assignee' => 'khaled'],
                    ['title' => 'تطبيق موبايل للمحامين والعملاء', 'desc' => 'تطبيقات iOS و Android مستقلة', 'priority' => 'medium', 'list' => 0, 'assignee' => 'khaled'],
                    ['title' => 'بروفايلات احترافية للمحامين', 'desc' => 'صفحات تعريفية لكل محامي', 'priority' => 'high', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'إطلاق مدونة قانونية متخصصة', 'desc' => 'مقالات قانونية أسبوعية تجذب الزوار', 'priority' => 'high', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'إنفوجرافيك قانوني للسوشيال ميديا', 'desc' => 'تبسيط مفاهيم قانونية بصرياً', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'فيديوهات شرح قانوني', 'desc' => 'فيديوهات قصيرة لشرح قوانين شائعة', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'بودكاست قانوني أسبوعي', 'desc' => 'حلقات تتناول قضايا قانونية معاصرة', 'priority' => 'low', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'حملة LinkedIn Ads مستهدفة', 'desc' => 'استهداف الشركات والمسؤولين القانونيين', 'priority' => 'high', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'إعلانات Google للكلمات القانونية', 'desc' => 'استشارة قانونية، محامي شركات، إلخ', 'priority' => 'high', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'إعلانات موجهة للشركات الناشئة', 'desc' => 'حملة لجذب الشركات للاستشارات القانونية', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'حملة Twitter/X للمحتوى القانوني', 'desc' => 'تغريدات يومية قانونية', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'تنظيم ندوات قانونية شهرية', 'desc' => 'ندوات Zoom مجانية لجذب العملاء', 'priority' => 'medium', 'list' => 0, 'assignee' => 'munther'],
                    ['title' => 'متابعة العملاء بعد الاستشارة', 'desc' => 'استطلاعات رضا وعروض متابعة', 'priority' => 'medium', 'list' => 1, 'assignee' => 'munther'],
                    ['title' => 'برنامج شراكة مع نقابات المحامين', 'desc' => 'بناء علاقات استراتيجية مع النقابات', 'priority' => 'low', 'list' => 0, 'assignee' => 'munther'],
                ],
            ],
        ];
    }
}
