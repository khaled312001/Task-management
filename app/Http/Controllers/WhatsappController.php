<?php

namespace App\Http\Controllers;

use App\Models\WhatsappSession;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class WhatsappController extends Controller
{
    // ========== API Settings ==========
    public function sessions()
    {
        return response()->json(
            WhatsappSession::where('user_id', Auth::id())->latest()->get()
        );
    }

    public function saveApiSettings(Request $request)
    {
        $request->validate([
            'provider' => 'required|string',
            'instance_id' => 'required|string',
            'api_token' => 'required|string',
        ]);

        $session = WhatsappSession::updateOrCreate(
            ['user_id' => Auth::id(), 'session_name' => 'api_' . $request->provider],
            [
                'status' => 'connected',
                'phone_number' => $request->instance_id,
                'qr_code' => json_encode([
                    'provider' => $request->provider,
                    'instance_id' => $request->instance_id,
                    'api_token' => $request->api_token,
                    'custom_url' => $request->custom_url ?? '',
                ]),
            ]
        );

        return response()->json(['success' => true, 'session' => $session]);
    }

    public function getApiSettings()
    {
        $session = WhatsappSession::where('user_id', Auth::id())->latest()->first();
        if (!$session) return response()->json(['configured' => false]);

        $config = json_decode($session->qr_code, true) ?? [];
        return response()->json([
            'configured' => true,
            'provider' => $config['provider'] ?? '',
            'instance_id' => $config['instance_id'] ?? '',
            'has_token' => !empty($config['api_token']),
        ]);
    }

    // ========== Send via Provider API ==========
    private function sendViaProvider($session, $phone, $message)
    {
        $config = json_decode($session->qr_code, true);
        $provider = $config['provider'] ?? 'ultramsg';
        $instanceId = $config['instance_id'] ?? '';
        $token = $config['api_token'] ?? '';

        $phone = preg_replace('/[^0-9]/', '', $phone);

        switch ($provider) {
            case 'ultramsg':
                $res = Http::asForm()->post("https://api.ultramsg.com/{$instanceId}/messages/chat", [
                    'token' => $token,
                    'to' => $phone,
                    'body' => $message,
                ]);
                $data = $res->json();
                if (!empty($data['sent']) && $data['sent'] === 'true') return ['success' => true];
                return ['success' => false, 'error' => $data['message'] ?? $data['error'] ?? 'Unknown error'];

            case 'fonnte':
                $res = Http::withHeaders(['Authorization' => $token])
                    ->asForm()->post('https://api.fonnte.com/send', [
                        'target' => $phone,
                        'message' => $message,
                    ]);
                $data = $res->json();
                if (!empty($data['status'])) return ['success' => true];
                return ['success' => false, 'error' => $data['reason'] ?? 'Unknown error'];

            case 'wapi':
                $res = Http::post("https://panel.wapi.chat/api/send", [
                    'token' => $token,
                    'phone' => $phone,
                    'message' => $message,
                ]);
                $data = $res->json();
                return ['success' => !empty($data['success']), 'error' => $data['error'] ?? ''];

            case 'custom':
                $url = $config['custom_url'] ?? '';
                if (!$url) return ['success' => false, 'error' => 'No custom URL'];
                $res = Http::post($url, [
                    'token' => $token,
                    'phone' => $phone,
                    'message' => $message,
                ]);
                $data = $res->json();
                return ['success' => $res->successful(), 'error' => $data['error'] ?? ''];

            default:
                return ['success' => false, 'error' => 'Unknown provider'];
        }
    }

    // ========== Campaigns ==========
    public function campaigns()
    {
        return response()->json(
            WhatsappCampaign::where('user_id', Auth::id())
                ->withCount('contacts')
                ->latest()->get()
        );
    }

    public function showCampaign(WhatsappCampaign $campaign)
    {
        $campaign->load('contacts');
        return response()->json($campaign);
    }

    public function storeCampaign(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        // Get active session
        $session = WhatsappSession::where('user_id', Auth::id())->latest()->first();

        $campaign = WhatsappCampaign::create([
            'user_id' => Auth::id(),
            'session_id' => $session?->id,
            'name' => $request->name,
            'message' => $request->message,
            'delay_seconds' => $request->delay_seconds ?? 5,
        ]);

        if ($request->has('phones')) {
            $phones = is_array($request->phones) ? $request->phones : explode("\n", $request->phones);
            $contacts = [];
            foreach ($phones as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = str_getcsv($line);
                $phone = preg_replace('/[^0-9+]/', '', $parts[0] ?? '');
                if (strlen($phone) >= 8) {
                    $contacts[] = [
                        'campaign_id' => $campaign->id,
                        'phone' => $phone,
                        'name' => trim($parts[1] ?? ''),
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (!empty($contacts)) {
                WhatsappContact::insert($contacts);
                $campaign->update(['total_recipients' => count($contacts)]);
            }
        }

        return response()->json($campaign->fresh(), 201);
    }

    public function importContacts(Request $request, WhatsappCampaign $campaign)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx']);
        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = null;
        $contacts = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (!$header) { $header = array_map('strtolower', array_map('trim', $row)); continue; }
            $data = array_combine($header, array_pad($row, count($header), ''));
            $phone = preg_replace('/[^0-9+]/', '', $data['phone'] ?? $data['mobile'] ?? $data['الهاتف'] ?? ($row[0] ?? ''));
            $name = $data['name'] ?? $data['الاسم'] ?? ($row[1] ?? '');
            if (strlen($phone) >= 8) {
                $contacts[] = [
                    'campaign_id' => $campaign->id, 'phone' => $phone,
                    'name' => trim($name), 'status' => 'pending',
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }
        fclose($handle);

        if (!empty($contacts)) {
            WhatsappContact::insert($contacts);
            $campaign->update(['total_recipients' => $campaign->contacts()->count()]);
        }

        return response()->json(['success' => true, 'imported' => count($contacts)]);
    }

    public function sendCampaign(WhatsappCampaign $campaign)
    {
        $session = WhatsappSession::where('user_id', Auth::id())->where('status', 'connected')->latest()->first();
        if (!$session) return response()->json(['error' => 'لم يتم إعداد WhatsApp API بعد. اذهب لتبويب إعدادات API'], 400);

        $pending = $campaign->contacts()->where('status', 'pending')->count();
        if ($pending === 0) return response()->json(['error' => 'لا توجد جهات اتصال للإرسال'], 400);

        $campaign->update(['status' => 'sending', 'session_id' => $session->id, 'sent_count' => 0, 'failed_count' => 0]);
        return response()->json(['success' => true]);
    }

    public function processBatchWa(WhatsappCampaign $campaign)
    {
        if ($campaign->status !== 'sending') {
            return response()->json(['done' => true, 'status' => $campaign->status]);
        }

        $session = WhatsappSession::find($campaign->session_id);
        if (!$session) {
            $campaign->update(['status' => 'failed']);
            return response()->json(['error' => 'Session not found'], 400);
        }

        $pending = WhatsappContact::where('campaign_id', $campaign->id)->where('status', 'pending')->limit(2)->get();

        if ($pending->isEmpty()) {
            $campaign->update(['status' => 'sent']);
            return response()->json(['done' => true, 'status' => 'sent']);
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($pending as $contact) {
            $msg = str_replace(
                ['{{name}}', '{{phone}}', '{{الاسم}}', '{{الرقم}}'],
                [$contact->name ?: 'عزيزي العميل', $contact->phone, $contact->name ?: 'عزيزي العميل', $contact->phone],
                $campaign->message
            );

            $result = $this->sendViaProvider($session, $contact->phone, $msg);

            if ($result['success']) {
                $contact->update(['status' => 'sent', 'sent_at' => now()]);
                $sentCount++;
            } else {
                $contact->update(['status' => 'failed', 'error_message' => $result['error'] ?? 'Unknown']);
                $failedCount++;
            }

            usleep($campaign->delay_seconds * 1000000);
        }

        $campaign->increment('sent_count', $sentCount);
        $campaign->increment('failed_count', $failedCount);
        $campaign->refresh();

        $remaining = WhatsappContact::where('campaign_id', $campaign->id)->where('status', 'pending')->count();
        if ($remaining === 0) $campaign->update(['status' => 'sent']);

        return response()->json([
            'done' => $remaining === 0,
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
            'total' => $campaign->total_recipients,
            'remaining' => $remaining,
        ]);
    }

    public function pauseCampaign(WhatsappCampaign $campaign)
    {
        $campaign->update(['status' => 'paused']);
        return response()->json(['success' => true]);
    }

    public function deleteCampaign(WhatsappCampaign $campaign)
    {
        $campaign->delete();
        return response()->json(['success' => true]);
    }

    // Keep old methods for route compatibility
    public function createSession(Request $request) { return $this->saveApiSettings($request); }
    public function getQr(WhatsappSession $session) { return response()->json($session); }
    public function sessionStatus(WhatsappSession $session) { return response()->json($session); }
    public function disconnectSession(WhatsappSession $session) {
        $session->delete();
        return response()->json(['success' => true]);
    }
}
