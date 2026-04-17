<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        return response()->json(
            Notification::where('user_id', Auth::id())->latest()->limit(50)->get()
        );
    }

    public function unreadCount()
    {
        return response()->json([
            'count' => Notification::where('user_id', Auth::id())->where('is_read', false)->count()
        ]);
    }

    public function markRead(Request $request)
    {
        $query = Notification::where('user_id', Auth::id());
        if ($request->id) {
            $query->where('id', $request->id);
        }
        $query->update(['is_read' => true]);
        return response()->json(['success' => true]);
    }
}
