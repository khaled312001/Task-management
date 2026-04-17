<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaign;
use App\Models\EmailLog;
use App\Models\SmtpSetting;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class EmailCampaignController extends Controller
{
    public function index()
    {
        return response()->json(
            EmailCampaign::where('user_id', Auth::id())
                ->with('contactList:id,name,contacts_count', 'smtpSetting:id,name,from_email')
                ->latest()->get()
        );
    }

    public function show(EmailCampaign $campaign)
    {
        $campaign->load('logs', 'contactList', 'smtpSetting');
        return response()->json($campaign);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'html_content' => 'required|string',
            'smtp_setting_id' => 'required|exists:smtp_settings,id',
            'list_id' => 'required|exists:contact_lists,id',
        ]);

        $campaign = EmailCampaign::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'subject' => $request->subject,
            'html_content' => $request->html_content,
            'smtp_setting_id' => $request->smtp_setting_id,
            'list_id' => $request->list_id,
            'delay_seconds' => $request->delay_seconds ?? 2,
        ]);

        return response()->json($campaign, 201);
    }

    public function update(Request $request, EmailCampaign $campaign)
    {
        $campaign->update($request->only([
            'name', 'subject', 'html_content', 'smtp_setting_id', 'list_id', 'delay_seconds',
        ]));
        return response()->json(['success' => true]);
    }

    public function destroy(EmailCampaign $campaign)
    {
        $campaign->delete();
        return response()->json(['success' => true]);
    }

    public function send(EmailCampaign $campaign)
    {
        if ($campaign->status === 'sending') {
            return response()->json(['error' => 'الحملة قيد الإرسال بالفعل'], 400);
        }

        $smtp = SmtpSetting::find($campaign->smtp_setting_id);
        if (!$smtp) return response()->json(['error' => 'لم يتم العثور على إعدادات SMTP'], 400);

        $contacts = Contact::where('list_id', $campaign->list_id)->where('is_subscribed', true)->get();
        if ($contacts->isEmpty()) return response()->json(['error' => 'لا توجد جهات اتصال'], 400);

        // Clear old logs and create new ones
        EmailLog::where('campaign_id', $campaign->id)->delete();
        $logs = [];
        foreach ($contacts as $c) {
            $logs[] = [
                'campaign_id' => $campaign->id,
                'contact_id' => $c->id,
                'email' => $c->email,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($logs, 500) as $chunk) {
            EmailLog::insert($chunk);
        }

        $campaign->update([
            'status' => 'sending',
            'total_recipients' => count($logs),
            'sent_count' => 0,
            'failed_count' => 0,
        ]);

        return response()->json([
            'success' => true,
            'total' => count($logs),
            'message' => 'بدأ إرسال الحملة لـ ' . count($logs) . ' جهة اتصال',
        ]);
    }

    /**
     * Process next batch of pending emails (called via AJAX polling)
     */
    public function processBatch(EmailCampaign $campaign)
    {
        if ($campaign->status !== 'sending') {
            return response()->json(['done' => true, 'status' => $campaign->status]);
        }

        $smtp = SmtpSetting::find($campaign->smtp_setting_id);
        if (!$smtp) {
            $campaign->update(['status' => 'failed']);
            return response()->json(['error' => 'SMTP not found'], 400);
        }

        $pending = EmailLog::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->limit(5)
            ->get();

        if ($pending->isEmpty()) {
            $campaign->update(['status' => 'sent', 'sent_at' => now()]);
            return response()->json(['done' => true, 'status' => 'sent']);
        }

        try {
            $password = Crypt::decryptString($smtp->password);
            $transport = new EsmtpTransport($smtp->host, $smtp->port, $smtp->encryption === 'ssl');
            $transport->setUsername($smtp->username);
            $transport->setPassword($password);
            $mailer = new Mailer($transport);
        } catch (\Exception $e) {
            $campaign->update(['status' => 'failed']);
            return response()->json(['error' => 'SMTP connection failed: ' . $e->getMessage()], 400);
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($pending as $log) {
            try {
                // Replace placeholders
                $html = $campaign->html_content;
                $contact = Contact::find($log->contact_id);
                if ($contact) {
                    $html = str_replace(['{{name}}', '{{email}}', '{{الاسم}}', '{{البريد}}'],
                        [$contact->name ?: 'عزيزي العميل', $contact->email, $contact->name ?: 'عزيزي العميل', $contact->email], $html);
                }

                $email = (new Email())
                    ->from(new Address($smtp->from_email, $smtp->from_name))
                    ->to($log->email)
                    ->subject($campaign->subject)
                    ->html($html);

                $mailer->send($email);

                $log->update(['status' => 'sent', 'sent_at' => now()]);
                $sentCount++;

                // Delay between emails
                if ($campaign->delay_seconds > 0) {
                    usleep($campaign->delay_seconds * 200000); // Partial delay per batch item
                }
            } catch (\Exception $e) {
                $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $failedCount++;
            }
        }

        $campaign->increment('sent_count', $sentCount);
        $campaign->increment('failed_count', $failedCount);
        $campaign->refresh();

        $remaining = EmailLog::where('campaign_id', $campaign->id)->where('status', 'pending')->count();
        if ($remaining === 0) {
            $campaign->update(['status' => 'sent', 'sent_at' => now()]);
        }

        return response()->json([
            'done' => $remaining === 0,
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
            'total' => $campaign->total_recipients,
            'remaining' => $remaining,
            'status' => $campaign->status,
        ]);
    }

    public function pause(EmailCampaign $campaign)
    {
        $campaign->update(['status' => 'paused']);
        return response()->json(['success' => true]);
    }

    public function resume(EmailCampaign $campaign)
    {
        $campaign->update(['status' => 'sending']);
        return response()->json(['success' => true]);
    }

    public function preview(Request $request)
    {
        return response($request->html_content ?? '')->header('Content-Type', 'text/html');
    }

    public function templates()
    {
        $templates = [
            [
                'id' => 1, 'name' => 'عرض خاص',
                'thumbnail' => 'promotion',
                'html' => $this->getTemplate('promotion'),
            ],
            [
                'id' => 2, 'name' => 'نشرة إخبارية',
                'thumbnail' => 'newsletter',
                'html' => $this->getTemplate('newsletter'),
            ],
            [
                'id' => 3, 'name' => 'ترحيب',
                'thumbnail' => 'welcome',
                'html' => $this->getTemplate('welcome'),
            ],
            [
                'id' => 4, 'name' => 'فارغ',
                'thumbnail' => 'blank',
                'html' => $this->getTemplate('blank'),
            ],
        ];
        return response()->json($templates);
    }

    private function getTemplate($type): string
    {
        $base = '<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head><body style="margin:0;padding:0;background:#f4f4f7;font-family:Tahoma,Arial,sans-serif">';
        $end = '</body></html>';
        $wrapper = '<div style="max-width:600px;margin:0 auto;background:#ffffff">';
        $wrapperEnd = '</div>';

        switch ($type) {
            case 'promotion':
                return $base . $wrapper . '
                <div style="background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:40px 30px;text-align:center">
                    <h1 style="color:#fff;font-size:28px;margin:0 0 10px">عرض خاص لك!</h1>
                    <p style="color:rgba(255,255,255,0.8);font-size:16px;margin:0">لفترة محدودة فقط</p>
                </div>
                <div style="padding:40px 30px;text-align:center">
                    <h2 style="color:#1e293b;font-size:22px;margin:0 0 15px">مرحباً {{name}}</h2>
                    <p style="color:#64748b;font-size:15px;line-height:1.8;margin:0 0 25px">نقدم لك عرضاً حصرياً على خدماتنا. لا تفوت هذه الفرصة الرائعة!</p>
                    <div style="background:#f8fafc;border-radius:12px;padding:25px;margin:0 0 25px">
                        <p style="font-size:36px;font-weight:bold;color:#6366f1;margin:0 0 5px">خصم 50%</p>
                        <p style="color:#94a3b8;margin:0">على جميع خدماتنا</p>
                    </div>
                    <a href="#" style="display:inline-block;background:#6366f1;color:#fff;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px">احصل على العرض</a>
                </div>
                <div style="background:#f8fafc;padding:20px 30px;text-align:center;font-size:12px;color:#94a3b8">
                    <p>تم الإرسال بواسطة Barmagly Tasks</p>
                </div>' . $wrapperEnd . $end;

            case 'newsletter':
                return $base . $wrapper . '
                <div style="background:#1e293b;padding:25px 30px;text-align:center">
                    <h1 style="color:#fff;font-size:24px;margin:0">النشرة الإخبارية</h1>
                </div>
                <div style="padding:30px">
                    <p style="color:#64748b;font-size:15px;margin:0 0 20px">مرحباً {{name}}،</p>
                    <p style="color:#334155;font-size:15px;line-height:1.8;margin:0 0 25px">إليك آخر الأخبار والتحديثات من فريقنا.</p>
                    <div style="border-right:4px solid #6366f1;padding:15px 20px;background:#f8fafc;margin:0 0 20px;border-radius:0 8px 8px 0">
                        <h3 style="color:#1e293b;margin:0 0 8px;font-size:16px">عنوان الخبر الأول</h3>
                        <p style="color:#64748b;font-size:14px;line-height:1.6;margin:0">اكتب هنا تفاصيل الخبر الأول...</p>
                    </div>
                    <div style="border-right:4px solid #10b981;padding:15px 20px;background:#f8fafc;margin:0 0 20px;border-radius:0 8px 8px 0">
                        <h3 style="color:#1e293b;margin:0 0 8px;font-size:16px">عنوان الخبر الثاني</h3>
                        <p style="color:#64748b;font-size:14px;line-height:1.6;margin:0">اكتب هنا تفاصيل الخبر الثاني...</p>
                    </div>
                </div>
                <div style="background:#f8fafc;padding:20px 30px;text-align:center;font-size:12px;color:#94a3b8">
                    <p>تم الإرسال بواسطة Barmagly Tasks</p>
                </div>' . $wrapperEnd . $end;

            case 'welcome':
                return $base . $wrapper . '
                <div style="background:linear-gradient(135deg,#10b981,#059669);padding:40px 30px;text-align:center">
                    <div style="font-size:48px;margin:0 0 15px">🎉</div>
                    <h1 style="color:#fff;font-size:28px;margin:0 0 10px">أهلاً وسهلاً!</h1>
                </div>
                <div style="padding:40px 30px;text-align:center">
                    <h2 style="color:#1e293b;font-size:20px;margin:0 0 15px">مرحباً بك {{name}}</h2>
                    <p style="color:#64748b;font-size:15px;line-height:1.8;margin:0 0 25px">يسعدنا انضمامك إلينا! نحن هنا لمساعدتك في كل ما تحتاجه.</p>
                    <a href="#" style="display:inline-block;background:#10b981;color:#fff;padding:14px 40px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px">ابدأ الآن</a>
                </div>
                <div style="background:#f8fafc;padding:20px 30px;text-align:center;font-size:12px;color:#94a3b8">
                    <p>تم الإرسال بواسطة Barmagly Tasks</p>
                </div>' . $wrapperEnd . $end;

            default: // blank
                return $base . $wrapper . '
                <div style="padding:40px 30px;text-align:center">
                    <h2 style="color:#1e293b;font-size:22px;margin:0 0 15px">عنوان الرسالة</h2>
                    <p style="color:#64748b;font-size:15px;line-height:1.8;margin:0 0 25px">مرحباً {{name}}، اكتب محتوى رسالتك هنا...</p>
                </div>' . $wrapperEnd . $end;
        }
    }
}
