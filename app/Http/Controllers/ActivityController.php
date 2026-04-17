<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user:id,full_name,avatar_color', 'board:id,name');

        if ($request->filled('board_id')) {
            $query->where('board_id', $request->board_id);
        } else {
            $boardIds = Auth::user()->boards()->pluck('boards.id');
            $query->whereIn('board_id', $boardIds);
        }

        return response()->json($query->latest()->limit($request->limit ?? 30)->get());
    }
}
