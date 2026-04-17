<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\BoardListController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\SmtpController;
use App\Http\Controllers\ContactListController;
use App\Http\Controllers\EmailCampaignController;
use App\Http\Controllers\WhatsappController;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// App (requires auth)
Route::middleware('auth')->group(function () {
    Route::get('/', fn() => view('app'))->name('home');
    Route::get('/me', [AuthController::class, 'me']);

    // Boards
    Route::get('/api/boards', [BoardController::class, 'index']);
    Route::get('/api/boards/{board}', [BoardController::class, 'show']);
    Route::post('/api/boards', [BoardController::class, 'store']);
    Route::put('/api/boards/{board}', [BoardController::class, 'update']);
    Route::post('/api/boards/{board}/archive', [BoardController::class, 'archive']);

    // Lists
    Route::post('/api/lists', [BoardListController::class, 'store']);
    Route::put('/api/lists/{list}', [BoardListController::class, 'update']);
    Route::post('/api/lists/reorder', [BoardListController::class, 'reorder']);
    Route::delete('/api/lists/{list}', [BoardListController::class, 'destroy']);

    // Tasks
    Route::get('/api/tasks/my', [TaskController::class, 'myTasks']);
    Route::get('/api/tasks/{task}', [TaskController::class, 'show']);
    Route::post('/api/tasks', [TaskController::class, 'store']);
    Route::put('/api/tasks/{task}', [TaskController::class, 'update']);
    Route::post('/api/tasks/{task}/move', [TaskController::class, 'move']);
    Route::post('/api/tasks/{task}/archive', [TaskController::class, 'archive']);
    Route::delete('/api/tasks/{task}', [TaskController::class, 'destroy']);

    // Comments
    Route::post('/api/comments', [CommentController::class, 'store']);
    Route::delete('/api/comments/{comment}', [CommentController::class, 'destroy']);

    // Checklists
    Route::post('/api/checklists', [ChecklistController::class, 'store']);
    Route::post('/api/checklists/{checklist}/toggle', [ChecklistController::class, 'toggle']);
    Route::delete('/api/checklists/{checklist}', [ChecklistController::class, 'destroy']);

    // Notifications
    Route::get('/api/notifications', [NotificationController::class, 'index']);
    Route::get('/api/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/api/notifications/mark-read', [NotificationController::class, 'markRead']);

    // Users
    Route::get('/api/users', [UserController::class, 'index']);
    Route::post('/api/users', [UserController::class, 'store']);

    // Activity
    Route::get('/api/activity', [ActivityController::class, 'index']);

    // ===== Email Marketing =====
    // SMTP Settings
    Route::get('/api/smtp', [SmtpController::class, 'index']);
    Route::post('/api/smtp', [SmtpController::class, 'store']);
    Route::put('/api/smtp/{smtp}', [SmtpController::class, 'update']);
    Route::delete('/api/smtp/{smtp}', [SmtpController::class, 'destroy']);
    Route::post('/api/smtp/{smtp}/test', [SmtpController::class, 'test']);

    // Contact Lists
    Route::get('/api/contact-lists', [ContactListController::class, 'index']);
    Route::get('/api/contact-lists/{contactList}', [ContactListController::class, 'show']);
    Route::post('/api/contact-lists', [ContactListController::class, 'store']);
    Route::delete('/api/contact-lists/{contactList}', [ContactListController::class, 'destroy']);
    Route::post('/api/contact-lists/{contactList}/import', [ContactListController::class, 'import']);
    Route::post('/api/contact-lists/{contactList}/add', [ContactListController::class, 'addManual']);
    Route::delete('/api/contacts/{contact}', [ContactListController::class, 'removeContact']);

    // Email Campaigns
    Route::get('/api/email-campaigns', [EmailCampaignController::class, 'index']);
    Route::get('/api/email-campaigns/templates', [EmailCampaignController::class, 'templates']);
    Route::get('/api/email-campaigns/{campaign}', [EmailCampaignController::class, 'show']);
    Route::post('/api/email-campaigns', [EmailCampaignController::class, 'store']);
    Route::put('/api/email-campaigns/{campaign}', [EmailCampaignController::class, 'update']);
    Route::delete('/api/email-campaigns/{campaign}', [EmailCampaignController::class, 'destroy']);
    Route::post('/api/email-campaigns/{campaign}/send', [EmailCampaignController::class, 'send']);
    Route::post('/api/email-campaigns/{campaign}/process', [EmailCampaignController::class, 'processBatch']);
    Route::post('/api/email-campaigns/{campaign}/pause', [EmailCampaignController::class, 'pause']);
    Route::post('/api/email-campaigns/{campaign}/resume', [EmailCampaignController::class, 'resume']);
    Route::post('/api/email-campaigns/preview', [EmailCampaignController::class, 'preview']);

    // ===== WhatsApp =====
    Route::get('/api/whatsapp/sessions', [WhatsappController::class, 'sessions']);
    Route::post('/api/whatsapp/sessions', [WhatsappController::class, 'createSession']);
    Route::get('/api/whatsapp/api-settings', [WhatsappController::class, 'getApiSettings']);
    Route::post('/api/whatsapp/api-settings', [WhatsappController::class, 'saveApiSettings']);
    Route::get('/api/whatsapp/sessions/{session}/qr', [WhatsappController::class, 'getQr']);
    Route::get('/api/whatsapp/sessions/{session}/status', [WhatsappController::class, 'sessionStatus']);
    Route::post('/api/whatsapp/sessions/{session}/disconnect', [WhatsappController::class, 'disconnectSession']);

    Route::get('/api/whatsapp/campaigns', [WhatsappController::class, 'campaigns']);
    Route::get('/api/whatsapp/campaigns/{campaign}', [WhatsappController::class, 'showCampaign']);
    Route::post('/api/whatsapp/campaigns', [WhatsappController::class, 'storeCampaign']);
    Route::post('/api/whatsapp/campaigns/{campaign}/import', [WhatsappController::class, 'importContacts']);
    Route::post('/api/whatsapp/campaigns/{campaign}/send', [WhatsappController::class, 'sendCampaign']);
    Route::post('/api/whatsapp/campaigns/{campaign}/process', [WhatsappController::class, 'processBatchWa']);
    Route::post('/api/whatsapp/campaigns/{campaign}/pause', [WhatsappController::class, 'pauseCampaign']);
    Route::delete('/api/whatsapp/campaigns/{campaign}', [WhatsappController::class, 'deleteCampaign']);
});
