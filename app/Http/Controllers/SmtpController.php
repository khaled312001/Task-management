<?php

namespace App\Http\Controllers;

use App\Models\SmtpSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class SmtpController extends Controller
{
    public function index()
    {
        return response()->json(
            SmtpSetting::where('user_id', Auth::id())->get()->map(function ($s) {
                $s->password_masked = '••••••••';
                return $s;
            })
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'username' => 'required|string',
            'password' => 'required|string',
            'from_email' => 'required|email',
            'from_name' => 'required|string',
        ]);

        $smtp = SmtpSetting::create([
            'user_id' => Auth::id(),
            'name' => $request->name ?? 'Default',
            'host' => $request->host,
            'port' => $request->port,
            'encryption' => $request->encryption ?? 'tls',
            'username' => $request->username,
            'password' => Crypt::encryptString($request->password),
            'from_email' => $request->from_email,
            'from_name' => $request->from_name,
        ]);

        return response()->json($smtp, 201);
    }

    public function update(Request $request, SmtpSetting $smtp)
    {
        $data = $request->only(['name', 'host', 'port', 'encryption', 'username', 'from_email', 'from_name', 'is_active']);
        if ($request->filled('password')) {
            $data['password'] = Crypt::encryptString($request->password);
        }
        $smtp->update($data);
        return response()->json(['success' => true]);
    }

    public function destroy(SmtpSetting $smtp)
    {
        $smtp->delete();
        return response()->json(['success' => true]);
    }

    public function test(Request $request, SmtpSetting $smtp)
    {
        try {
            $password = Crypt::decryptString($smtp->password);
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $smtp->host, $smtp->port, $smtp->encryption === 'ssl'
            );
            $transport->setUsername($smtp->username);
            $transport->setPassword($password);

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($smtp->from_email, $smtp->from_name))
                ->to($request->test_email ?? $smtp->from_email)
                ->subject('اختبار SMTP - Barmagly Tasks')
                ->html('<div dir="rtl" style="font-family:Tahoma;padding:20px"><h2>تم الاتصال بنجاح!</h2><p>إعدادات SMTP تعمل بشكل صحيح.</p></div>');

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);
            $mailer->send($email);

            return response()->json(['success' => true, 'message' => 'تم إرسال الرسالة التجريبية بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشل الاتصال: ' . $e->getMessage()], 400);
        }
    }
}
