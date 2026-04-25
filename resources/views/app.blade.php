<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Barmagly Tasks - إدارة المهام</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📋</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    @php
        // Use jsDelivr CDN to serve CSS/JS from GitHub for reliable delivery on Vercel
        $cdnBase = 'https://cdn.jsdelivr.net/gh/khaled312001/Task-management@main/public';
        $isLocal = !str_contains(request()->getHost(), 'vercel.app');
    @endphp
    <link rel="stylesheet" href="{{ $isLocal ? asset('css/app.css').'?v='.time() : $cdnBase.'/css/app.css' }}">
</head>
<body>
<div id="app" class="app">
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <div class="logo"><i class="fas fa-tasks"></i><span>Barmagly</span></div>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>
        <div class="sidebar-user">
            <div class="user-avatar" id="sidebarAvatar"></div>
            <div class="user-info">
                <span class="user-name" id="sidebarUserName"></span>
                <span class="user-role" id="sidebarUserRole"></span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="#" class="nav-item active" data-view="boards"><i class="fas fa-th-large"></i><span>لوحات المشاريع</span></a>
            <a href="#" class="nav-item" data-view="my-tasks"><i class="fas fa-user-check"></i><span>مهامي</span></a>
            <a href="#" class="nav-item" data-view="activity"><i class="fas fa-history"></i><span>سجل النشاط</span></a>
            <a href="#" class="nav-item" data-view="team"><i class="fas fa-users"></i><span>الفريق</span></a>

            <div class="nav-divider"></div>
            <div class="nav-section-label">الخدمات</div>
            <a href="#" class="nav-item" data-view="email-marketing"><i class="fas fa-envelope-open-text"></i><span>البريد التسويقي</span></a>
            <a href="#" class="nav-item" data-view="whatsapp"><i class="fab fa-whatsapp"></i><span>واتساب</span></a>
        </nav>
        <div class="sidebar-boards">
            <div class="sidebar-section-title"><span>المشاريع</span>
                <button class="btn-icon" id="addBoardSidebarBtn" title="مشروع جديد"><i class="fas fa-plus"></i></button>
            </div>
            <div id="boardsList"></div>
        </div>
        <div class="sidebar-footer">
            <form action="/logout" method="POST" style="width:100%">@csrf
                <button type="submit" class="nav-item" style="width:100%;border:none;background:none;cursor:pointer"><i class="fas fa-sign-out-alt"></i><span>تسجيل الخروج</span></button>
            </form>
        </div>
    </aside>

    <!-- Main -->
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-right">
                <button class="btn-icon sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <h2 class="page-title" id="pageTitle">لوحات المشاريع</h2>
            </div>
            <div class="topbar-left">
                <div class="search-box"><i class="fas fa-search"></i><input type="text" id="globalSearch" placeholder="بحث..."></div>
                <div class="notification-bell" id="notifBell"><i class="fas fa-bell"></i><span class="notif-badge hidden" id="notifBadge">0</span></div>
                <div class="topbar-avatar" id="topbarAvatar"></div>
            </div>
        </header>

        <div class="view-container" id="viewContainer">
            <!-- Boards View -->
            <div class="view active" id="boardsView">
                <div class="boards-header">
                    <div class="boards-filters">
                        <button class="filter-btn active" data-filter="all">الكل</button>
                        <button class="filter-btn" data-filter="development">التطوير</button>
                        <button class="filter-btn" data-filter="marketing">التسويق</button>
                    </div>
                    <button class="btn btn-primary" id="newBoardBtn"><i class="fas fa-plus"></i> <span>مشروع جديد</span></button>
                </div>
                <div class="boards-grid" id="boardsGrid"></div>
            </div>

            <!-- Kanban View -->
            <div class="view" id="kanbanView">
                <div class="kanban-header">
                    <div class="kanban-header-right">
                        <button class="btn-icon" id="backToBoards"><i class="fas fa-arrow-right"></i></button>
                        <h3 id="kanbanTitle"></h3>
                        <span class="board-category-badge" id="kanbanCategory"></span>
                    </div>
                    <div class="kanban-header-left">
                        <div class="kanban-members" id="kanbanMembers"></div>
                        <button class="btn btn-sm btn-outline" id="kanbanFilterBtn"><i class="fas fa-filter"></i> <span class="hide-mobile">فلتر</span></button>
                        <button class="btn btn-sm btn-outline" id="addListBtn"><i class="fas fa-plus"></i> <span class="hide-mobile">قائمة</span></button>
                    </div>
                </div>
                <div class="kanban-filter-bar hidden" id="kanbanFilterBar">
                    <select id="filterPriority"><option value="">كل الأولويات</option><option value="urgent">عاجل</option><option value="high">مرتفع</option><option value="medium">متوسط</option><option value="low">منخفض</option></select>
                    <select id="filterAssigned"><option value="">كل الأعضاء</option></select>
                    <select id="filterLabel"><option value="">كل التصنيفات</option></select>
                </div>
                <div class="kanban-board" id="kanbanBoard"></div>
            </div>

            <!-- My Tasks View -->
            <div class="view" id="myTasksView">
                <div class="my-tasks-header">
                    <h3>مهامي</h3>
                    <div class="my-tasks-stats" id="myTasksStats"></div>
                </div>
                <div class="my-tasks-content" id="myTasksContent"></div>
            </div>

            <!-- Activity View -->
            <div class="view" id="activityView">
                <div class="activity-list" id="activityList"></div>
            </div>

            <!-- Team View -->
            <div class="view" id="teamView">
                <div class="team-header">
                    <h3>فريق العمل</h3>
                    <button class="btn btn-primary btn-sm" id="addMemberBtn"><i class="fas fa-user-plus"></i> إضافة عضو</button>
                </div>
                <div class="team-grid" id="teamGrid"></div>
            </div>

            <!-- ===== Email Marketing View ===== -->
            <div class="view" id="emailMarketingView">
                <div class="service-tabs">
                    <button class="service-tab active" data-etab="campaigns"><i class="fas fa-paper-plane"></i> الحملات</button>
                    <button class="service-tab" data-etab="contacts"><i class="fas fa-address-book"></i> جهات الاتصال</button>
                    <button class="service-tab" data-etab="smtp"><i class="fas fa-server"></i> إعدادات SMTP</button>
                </div>

                <!-- Campaigns Tab -->
                <div class="service-panel active" id="emailCampaignsPanel">
                    <div class="panel-header">
                        <h3>حملات البريد الإلكتروني</h3>
                        <button class="btn btn-primary" id="newCampaignBtn"><i class="fas fa-plus"></i> حملة جديدة</button>
                    </div>
                    <div id="campaignsList"></div>
                </div>

                <!-- Contacts Tab -->
                <div class="service-panel" id="emailContactsPanel">
                    <div class="panel-header">
                        <h3>قوائم جهات الاتصال</h3>
                        <button class="btn btn-primary" id="newContactListBtn"><i class="fas fa-plus"></i> قائمة جديدة</button>
                    </div>
                    <div id="contactListsContainer"></div>
                </div>

                <!-- SMTP Tab -->
                <div class="service-panel" id="emailSmtpPanel">
                    <div class="panel-header">
                        <h3>إعدادات SMTP</h3>
                        <button class="btn btn-primary" id="newSmtpBtn"><i class="fas fa-plus"></i> إضافة SMTP</button>
                    </div>
                    <div id="smtpListContainer"></div>
                </div>
            </div>

            <!-- ===== WhatsApp View ===== -->
            <div class="view" id="whatsappView">
                <div class="service-tabs">
                    <button class="service-tab active" data-wtab="wa-campaigns"><i class="fas fa-paper-plane"></i> الحملات</button>
                    <button class="service-tab" data-wtab="wa-settings"><i class="fas fa-cog"></i> إعدادات API</button>
                </div>

                <!-- WA Campaigns Tab -->
                <div class="service-panel active" id="waCampaignsPanel">
                    <div class="panel-header">
                        <h3>حملات الواتساب</h3>
                        <button class="btn btn-primary" id="newWaCampaignBtn"><i class="fas fa-plus"></i> حملة جديدة</button>
                    </div>
                    <div id="waCampaignsList"></div>
                </div>

                <!-- WA Settings Tab -->
                <div class="service-panel" id="waSettingsPanel">
                    <div class="wa-api-setup">
                        <div class="wa-info-card">
                            <div style="text-align:center;margin-bottom:20px">
                                <i class="fab fa-whatsapp" style="font-size:48px;color:#25d366"></i>
                                <h3 style="margin-top:10px">إعدادات WhatsApp API</h3>
                                <p style="color:var(--text-muted);font-size:13px;margin-top:6px">أدخل بيانات API الخاصة بك لإرسال رسائل الواتساب</p>
                            </div>
                            <form id="waApiForm">
                                <div class="form-group">
                                    <label>مزود الخدمة</label>
                                    <select id="waProvider">
                                        <option value="ultramsg">UltraMsg.com</option>
                                        <option value="fonnte">Fonnte.com</option>
                                        <option value="wapi">Wapi.chat</option>
                                        <option value="custom">Custom API</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Instance ID / Token</label>
                                    <input type="text" id="waInstanceId" placeholder="مثال: instance12345" style="direction:ltr">
                                </div>
                                <div class="form-group">
                                    <label>API Token / Secret</label>
                                    <input type="text" id="waApiToken" placeholder="أدخل الـ API Token" style="direction:ltr">
                                </div>
                                <div class="form-group hidden" id="waCustomUrlGroup">
                                    <label>Custom API URL</label>
                                    <input type="text" id="waCustomUrl" placeholder="https://api.example.com/send" style="direction:ltr">
                                </div>
                                <button type="submit" class="btn btn-primary btn-block" style="background:#25d366"><i class="fab fa-whatsapp"></i> حفظ الإعدادات</button>
                            </form>
                            <div style="margin-top:20px;padding:16px;background:rgba(255,255,255,.03);border-radius:8px;font-size:12px;color:var(--text-muted);line-height:1.8">
                                <strong style="color:var(--text-secondary)">كيف أحصل على API؟</strong><br>
                                1. سجل في <span style="color:#25d366">UltraMsg.com</span> (مجاني للتجربة)<br>
                                2. اربط رقم الواتساب عبر QR Code في لوحة تحكمهم<br>
                                3. انسخ الـ Instance ID و Token وضعهم هنا<br>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden elements for compatibility -->
                <div style="display:none">
                    <button id="newWaSessionBtn"></button>
                    <div id="waSessionsList"></div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Task Detail Modal -->
<div class="modal-overlay hidden" id="taskModal">
    <div class="modal task-modal">
        <div class="modal-header">
            <div class="task-modal-title-wrap"><i class="fas fa-clipboard-check"></i><input type="text" class="task-title-input" id="taskTitleInput"></div>
            <button class="modal-close" id="taskModalClose"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="task-modal-main">
                <div class="task-section">
                    <h4><i class="fas fa-tags"></i> التصنيفات</h4>
                    <div class="task-labels-wrap" id="taskLabels"></div>
                </div>
                <div class="task-section">
                    <h4><i class="fas fa-align-right"></i> الوصف</h4>
                    <textarea id="taskDescription" class="task-description" placeholder="أضف وصفاً للمهمة..."></textarea>
                </div>
                <div class="task-section">
                    <h4><i class="fas fa-check-square"></i> قائمة المهام الفرعية</h4>
                    <div class="checklist-progress" id="checklistProgress"></div>
                    <div id="taskChecklist"></div>
                    <div class="checklist-add">
                        <input type="text" id="checklistInput" placeholder="أضف عنصر جديد...">
                        <button class="btn btn-sm btn-primary" id="addChecklistBtn"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="task-section">
                    <h4><i class="fas fa-comments"></i> التعليقات</h4>
                    <div class="comment-form">
                        <textarea id="commentInput" placeholder="اكتب تعليقاً..."></textarea>
                        <button class="btn btn-sm btn-primary" id="addCommentBtn">إرسال</button>
                    </div>
                    <div id="taskComments"></div>
                </div>
                <div class="task-section">
                    <h4><i class="fas fa-history"></i> النشاط</h4>
                    <div id="taskActivity"></div>
                </div>
            </div>
            <div class="task-modal-sidebar">
                <div class="task-detail-group"><label>الحالة</label>
                    <select id="taskStatus"><option value="pending">قيد الانتظار</option><option value="in_progress">قيد التنفيذ</option><option value="review">مراجعة</option><option value="completed">مكتمل</option><option value="blocked">محظور</option></select>
                </div>
                <div class="task-detail-group"><label>الأولوية</label>
                    <select id="taskPriority"><option value="low">منخفض</option><option value="medium">متوسط</option><option value="high">مرتفع</option><option value="urgent">عاجل</option></select>
                </div>
                <div class="task-detail-group"><label>مسند إلى</label><select id="taskAssigned"></select></div>
                <div class="task-detail-group"><label>تاريخ الاستحقاق</label><input type="date" id="taskDueDate"></div>
                <div class="task-detail-group"><label>الساعات المقدرة</label><input type="number" id="taskEstHours" step="0.5" min="0"></div>
                <div class="task-detail-group"><label>الساعات الفعلية</label><input type="number" id="taskActHours" step="0.5" min="0"></div>
                <div class="task-actions">
                    <button class="btn btn-sm btn-outline btn-danger" id="archiveTaskBtn"><i class="fas fa-archive"></i> أرشفة</button>
                    <button class="btn btn-sm btn-outline btn-danger" id="deleteTaskBtn"><i class="fas fa-trash"></i> حذف</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Board Modal -->
<div class="modal-overlay hidden" id="boardModal">
    <div class="modal small-modal">
        <div class="modal-header"><h3>مشروع جديد</h3><button class="modal-close" id="boardModalClose"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form id="boardForm">
                <div class="form-group"><label>اسم المشروع</label><input type="text" id="boardName" required placeholder="مثال: موقع العميل"></div>
                <div class="form-group"><label>الوصف</label><textarea id="boardDesc" placeholder="وصف المشروع..."></textarea></div>
                <div class="form-group"><label>التصنيف</label>
                    <select id="boardCategory"><option value="development">تطوير</option><option value="marketing">تسويق</option><option value="general">عام</option></select>
                </div>
                <div class="form-group"><label>اللون</label>
                    <div class="color-picker" id="boardColorPicker">
                        <span class="color-option selected" data-color="#6366f1" style="background:#6366f1"></span>
                        <span class="color-option" data-color="#10b981" style="background:#10b981"></span>
                        <span class="color-option" data-color="#f59e0b" style="background:#f59e0b"></span>
                        <span class="color-option" data-color="#ef4444" style="background:#ef4444"></span>
                        <span class="color-option" data-color="#3b82f6" style="background:#3b82f6"></span>
                        <span class="color-option" data-color="#8b5cf6" style="background:#8b5cf6"></span>
                        <span class="color-option" data-color="#ec4899" style="background:#ec4899"></span>
                        <span class="color-option" data-color="#14b8a6" style="background:#14b8a6"></span>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-plus"></i> إنشاء المشروع</button>
            </form>
        </div>
    </div>
</div>

<!-- New Task Modal -->
<div class="modal-overlay hidden" id="newTaskModal">
    <div class="modal small-modal">
        <div class="modal-header"><h3>مهمة جديدة</h3><button class="modal-close" id="newTaskModalClose"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form id="newTaskForm">
                <input type="hidden" id="newTaskListId"><input type="hidden" id="newTaskBoardId">
                <div class="form-group"><label>عنوان المهمة</label><input type="text" id="newTaskTitle" required placeholder="ماذا يجب فعله؟"></div>
                <div class="form-group"><label>الوصف</label><textarea id="newTaskDesc" placeholder="تفاصيل المهمة..."></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>الأولوية</label>
                        <select id="newTaskPriority"><option value="low">منخفض</option><option value="medium" selected>متوسط</option><option value="high">مرتفع</option><option value="urgent">عاجل</option></select>
                    </div>
                    <div class="form-group"><label>مسند إلى</label><select id="newTaskAssigned"></select></div>
                </div>
                <div class="form-group"><label>تاريخ الاستحقاق</label><input type="date" id="newTaskDueDate"></div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-plus"></i> إضافة المهمة</button>
            </form>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal-overlay hidden" id="memberModal">
    <div class="modal small-modal">
        <div class="modal-header"><h3>إضافة عضو جديد</h3><button class="modal-close" id="memberModalClose"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form id="memberForm">
                <div class="form-group"><label>اسم المستخدم</label><input type="text" id="memberUsername" required></div>
                <div class="form-group"><label>الاسم الكامل</label><input type="text" id="memberFullName" required></div>
                <div class="form-group"><label>البريد الإلكتروني</label><input type="email" id="memberEmail"></div>
                <div class="form-group"><label>كلمة المرور</label><input type="password" id="memberPassword" required></div>
                <div class="form-group"><label>الدور</label>
                    <select id="memberRole"><option value="developer">مطور</option><option value="manager">مدير مشروع</option><option value="marketer">مسوق</option></select>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-user-plus"></i> إضافة العضو</button>
            </form>
        </div>
    </div>
</div>

<!-- Add List Modal -->
<div class="modal-overlay hidden" id="listModal">
    <div class="modal small-modal">
        <div class="modal-header"><h3>قائمة جديدة</h3><button class="modal-close" id="listModalClose"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form id="listForm">
                <div class="form-group"><label>اسم القائمة</label><input type="text" id="listName" required placeholder="مثال: قيد المراجعة"></div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-plus"></i> إنشاء القائمة</button>
            </form>
        </div>
    </div>
</div>

<!-- Notification Panel -->
<div class="notif-panel hidden" id="notifPanel">
    <div class="notif-header"><h4>الإشعارات</h4><button class="btn-link" id="markAllRead">تحديد الكل كمقروء</button></div>
    <div class="notif-list" id="notifList"></div>
</div>

<!-- ===== SMTP Modal ===== -->
<div class="modal-overlay hidden" id="smtpModal">
    <div class="modal small-modal">
        <div class="modal-header"><h3>إعدادات SMTP</h3><button class="modal-close" onclick="document.getElementById('smtpModal').classList.add('hidden')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form id="smtpForm">
                <input type="hidden" id="smtpId">
                <div class="form-group"><label>الاسم</label><input type="text" id="smtpName" value="Default" required></div>
                <div class="form-row">
                    <div class="form-group"><label>SMTP Host</label><input type="text" id="smtpHost" required placeholder="smtp.gmail.com"></div>
                    <div class="form-group" style="max-width:100px"><label>Port</label><input type="number" id="smtpPort" value="587" required></div>
                </div>
                <div class="form-group"><label>التشفير</label>
                    <select id="smtpEncryption"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select>
                </div>
                <div class="form-group"><label>اسم المستخدم / البريد</label><input type="text" id="smtpUsername" required placeholder="your@email.com"></div>
                <div class="form-group"><label>كلمة المرور</label><input type="password" id="smtpPassword" required placeholder="App Password"></div>
                <div class="form-row">
                    <div class="form-group"><label>بريد المرسل</label><input type="email" id="smtpFromEmail" required></div>
                    <div class="form-group"><label>اسم المرسل</label><input type="text" id="smtpFromName" required placeholder="Barmagly"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> حفظ</button>
            </form>
        </div>
    </div>
</div>

<!-- ===== Contact List Modal ===== -->
<div class="modal-overlay hidden" id="contactListModal">
    <div class="modal small-modal">
        <div class="modal-header"><h3>قائمة جهات اتصال</h3><button class="modal-close" onclick="document.getElementById('contactListModal').classList.add('hidden')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <form id="contactListForm">
                <div class="form-group"><label>اسم القائمة</label><input type="text" id="clName" required placeholder="مثال: عملاء 2026"></div>
                <div class="form-group"><label>الوصف</label><textarea id="clDesc" placeholder="وصف القائمة..."></textarea></div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-plus"></i> إنشاء القائمة</button>
            </form>
        </div>
    </div>
</div>

<!-- ===== Contact List Detail Modal ===== -->
<div class="modal-overlay hidden" id="contactDetailModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header">
            <h3 id="contactDetailTitle">جهات الاتصال</h3>
            <button class="modal-close" onclick="document.getElementById('contactDetailModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="contact-import-section">
                <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:16px">
                    <div style="flex:1;min-width:200px">
                        <label style="font-size:13px;color:var(--text-muted);display:block;margin-bottom:6px">استيراد من ملف CSV</label>
                        <input type="file" id="contactFileInput" accept=".csv,.txt,.xlsx" style="font-size:13px">
                    </div>
                    <button class="btn btn-primary btn-sm" id="importContactsBtn"><i class="fas fa-file-import"></i> استيراد</button>
                </div>
                <div class="manual-add-row" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
                    <input type="text" id="manualName" placeholder="الاسم" style="flex:1;min-width:120px;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--bg-input);color:var(--text-primary);font-size:13px">
                    <input type="email" id="manualEmail" placeholder="البريد الإلكتروني" required style="flex:2;min-width:180px;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--bg-input);color:var(--text-primary);font-size:13px">
                    <input type="text" id="manualPhone" placeholder="الهاتف" style="flex:1;min-width:120px;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--bg-input);color:var(--text-primary);font-size:13px">
                    <button class="btn btn-primary btn-sm" id="addManualContactBtn"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            <div id="contactsTable"></div>
        </div>
    </div>
</div>

<!-- ===== Email Campaign Builder Modal ===== -->
<div class="modal-overlay hidden" id="campaignModal">
    <div class="modal" style="max-width:1000px">
        <div class="modal-header">
            <h3 id="campaignModalTitle">حملة بريد جديدة</h3>
            <button class="modal-close" onclick="document.getElementById('campaignModal').classList.add('hidden')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="display:flex;gap:20px">
            <div style="flex:1;min-width:0">
                <div class="form-group"><label>اسم الحملة</label><input type="text" id="campName" required placeholder="مثال: عرض رمضان"></div>
                <div class="form-group"><label>عنوان الرسالة (Subject)</label><input type="text" id="campSubject" required placeholder="عنوان يجذب الانتباه"></div>
                <div class="form-row">
                    <div class="form-group"><label>حساب SMTP</label><select id="campSmtp"></select></div>
                    <div class="form-group"><label>قائمة جهات الاتصال</label><select id="campList"></select></div>
                </div>
                <div class="form-group">
                    <label>التأخير بين كل رسالة (ثواني)</label>
                    <input type="number" id="campDelay" value="2" min="1" max="60">
                </div>
                <div class="form-group">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <label style="margin:0">محتوى الرسالة (HTML)</label>
                        <div style="display:flex;gap:6px">
                            <button class="btn btn-sm btn-outline" id="loadTemplateBtn"><i class="fas fa-magic"></i> قوالب</button>
                            <button class="btn btn-sm btn-outline" id="previewEmailBtn"><i class="fas fa-eye"></i> معاينة</button>
                        </div>
                    </div>
                    <textarea id="campContent" style="min-height:300px;font-family:monospace;font-size:12px;direction:ltr;text-align:left" placeholder="اكتب HTML الرسالة هنا..."></textarea>
                </div>
                <div style="display:flex;gap:10px">
                    <button class="btn btn-primary btn-block" id="saveCampaignBtn"><i class="fas fa-save"></i> حفظ كمسودة</button>
                    <button class="btn btn-block" style="background:var(--success);color:#fff" id="sendCampaignBtn"><i class="fas fa-paper-plane"></i> إرسال الآن</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Templates Modal ===== -->
<div class="modal-overlay hidden" id="templatesModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header"><h3>قوالب البريد الإلكتروني</h3><button class="modal-close" onclick="document.getElementById('templatesModal').classList.add('hidden')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <div class="templates-grid" id="templatesGrid"></div>
        </div>
    </div>
</div>

<!-- ===== Email Preview Modal ===== -->
<div class="modal-overlay hidden" id="previewModal">
    <div class="modal" style="max-width:650px">
        <div class="modal-header"><h3>معاينة الرسالة</h3><button class="modal-close" onclick="document.getElementById('previewModal').classList.add('hidden')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body" style="padding:0">
            <iframe id="previewFrame" style="width:100%;height:500px;border:none;background:#fff"></iframe>
        </div>
    </div>
</div>

<!-- ===== Sending Progress Modal ===== -->
<div class="modal-overlay hidden" id="sendingModal">
    <div class="modal small-modal">
        <div class="modal-header"><h3 id="sendingTitle">جاري الإرسال...</h3><button class="modal-close" id="closeSendingBtn"><i class="fas fa-times"></i></button></div>
        <div class="modal-body" style="text-align:center;padding:30px">
            <div class="sending-progress-ring" id="sendingRing">
                <svg viewBox="0 0 120 120" width="120" height="120">
                    <circle cx="60" cy="60" r="54" fill="none" stroke="var(--border)" stroke-width="8"/>
                    <circle cx="60" cy="60" r="54" fill="none" stroke="var(--accent)" stroke-width="8" stroke-linecap="round" id="sendingCircle" style="stroke-dasharray:339.292;stroke-dashoffset:339.292;transition:stroke-dashoffset .5s;transform:rotate(-90deg);transform-origin:center"/>
                </svg>
                <div class="sending-pct" id="sendingPct">0%</div>
            </div>
            <div class="sending-stats" style="display:flex;justify-content:center;gap:24px;margin-top:20px">
                <div><div style="font-size:24px;font-weight:800;color:var(--success)" id="sendingSent">0</div><div style="font-size:12px;color:var(--text-muted)">تم الإرسال</div></div>
                <div><div style="font-size:24px;font-weight:800;color:var(--danger)" id="sendingFailed">0</div><div style="font-size:12px;color:var(--text-muted)">فشل</div></div>
                <div><div style="font-size:24px;font-weight:800;color:var(--text-secondary)" id="sendingRemaining">0</div><div style="font-size:12px;color:var(--text-muted)">متبقي</div></div>
            </div>
            <div style="margin-top:20px">
                <button class="btn btn-sm btn-outline btn-danger" id="pauseSendingBtn"><i class="fas fa-pause"></i> إيقاف مؤقت</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== WA Campaign Modal ===== -->
<div class="modal-overlay hidden" id="waCampaignModal">
    <div class="modal" style="max-width:700px">
        <div class="modal-header"><h3>حملة واتساب جديدة</h3><button class="modal-close" onclick="document.getElementById('waCampaignModal').classList.add('hidden')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body">
            <div class="form-group"><label>اسم الحملة</label><input type="text" id="waName" required placeholder="مثال: عرض العيد"></div>
            <div class="form-group"><label>جلسة الواتساب</label><select id="waSession"></select></div>
            <div class="form-group">
                <label>الرسالة <small style="color:var(--text-muted)">(يمكنك استخدام @{{name}} و @{{phone}})</small></label>
                <textarea id="waMessage" style="min-height:120px" placeholder="مرحباً @{{name}}، نود إخبارك بعرضنا الجديد..."></textarea>
            </div>
            <div class="form-group">
                <label>أرقام الهواتف <small style="color:var(--text-muted)">(رقم في كل سطر، أو رقم,اسم)</small></label>
                <textarea id="waPhones" style="min-height:100px;direction:ltr;text-align:left;font-family:monospace" placeholder="201234567890&#10;201098765432,أحمد&#10;966501234567,محمد"></textarea>
            </div>
            <div class="form-group">
                <label>أو استيراد من ملف CSV</label>
                <input type="file" id="waFileInput" accept=".csv,.txt" style="font-size:13px">
            </div>
            <div class="form-group">
                <label>التأخير بين كل رسالة (ثواني)</label>
                <input type="number" id="waDelay" value="5" min="3" max="120">
            </div>
            <div style="display:flex;gap:10px">
                <button class="btn btn-primary btn-block" id="saveWaCampaignBtn"><i class="fas fa-save"></i> حفظ</button>
                <button class="btn btn-block" style="background:#25d366;color:#fff" id="sendWaCampaignBtn"><i class="fab fa-whatsapp"></i> إرسال الآن</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== WA QR Modal ===== -->
<div class="modal-overlay hidden" id="waQrModal">
    <div class="modal small-modal">
        <div class="modal-header"><h3>ربط الواتساب</h3><button class="modal-close" onclick="document.getElementById('waQrModal').classList.add('hidden')"><i class="fas fa-times"></i></button></div>
        <div class="modal-body" style="text-align:center;padding:30px">
            <div id="waQrContent">
                <div class="wa-qr-loading"><i class="fas fa-spinner fa-spin" style="font-size:40px;color:var(--accent)"></i><p style="margin-top:16px;color:var(--text-muted)">جاري تجهيز QR Code...</p></div>
            </div>
            <p style="color:var(--text-muted);font-size:13px;margin-top:16px">افتح الواتساب على هاتفك > الأجهزة المرتبطة > ربط جهاز</p>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>window.APP_USER = @json(Auth::user());</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="{{ $isLocal ? asset('js/app.js').'?v='.time() : $cdnBase.'/js/app.js' }}"></script>
</body>
</html>
