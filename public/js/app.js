// ==================== Barmagly Tasks App ====================
const App = {
    user: window.APP_USER,
    currentBoard: null,
    currentTask: null,
    boards: [],
    users: [],
    csrfToken: document.querySelector('meta[name="csrf-token"]').content,

    // ==================== API Helper ====================
    async api(url, method = 'GET', data = null) {
        try {
            const opts = {
                method, headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                }
            };
            if (data) opts.body = JSON.stringify(data);
            const res = await fetch(url, opts);
            if (res.status === 401) { window.location.href = '/login'; return []; }
            const json = await res.json();
            return json;
        } catch (e) {
            console.error('API Error:', url, e);
            return [];
        }
    },

    // ==================== Init ====================
    async init() {
        try {
            this.setupUI();
            this.setupEvents();
            this.setupEmailEvents();
            this.setupWhatsappEvents();
            this.loadUser();
            await this.loadUsers();
            await this.loadBoards();
            this.pollNotifications();
        } catch (e) {
            console.error('Init Error:', e);
        }
    },

    setupUI() {
        const avatar = (el, user) => {
            if (!el || !user) return;
            el.textContent = user.full_name ? user.full_name.charAt(0) : '?';
            el.style.background = user.avatar_color || '#6366f1';
        };
        avatar(document.getElementById('sidebarAvatar'), this.user);
        avatar(document.getElementById('topbarAvatar'), this.user);
    },

    loadUser() {
        const u = this.user;
        document.getElementById('sidebarUserName').textContent = u.full_name;
        const roles = { admin: 'مسؤول', manager: 'مدير مشروع', developer: 'مطور', marketer: 'مسوق' };
        document.getElementById('sidebarUserRole').textContent = roles[u.role] || u.role;
    },

    // ==================== Events ====================
    setupEvents() {
        // Sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        document.getElementById('sidebarToggle').onclick = () => { sidebar.classList.add('open'); overlay.classList.add('show'); };
        document.getElementById('sidebarClose').onclick = () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); };
        overlay.onclick = () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); };

        // Nav items
        document.querySelectorAll('.nav-item[data-view]').forEach(item => {
            item.onclick = (e) => { e.preventDefault(); this.switchView(item.dataset.view); };
        });

        // Board filters
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.onclick = () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.renderBoards(btn.dataset.filter);
            };
        });

        // New board
        document.getElementById('newBoardBtn').onclick = () => this.openBoardModal();
        document.getElementById('addBoardSidebarBtn').onclick = () => this.openBoardModal();
        document.getElementById('boardModalClose').onclick = () => this.closeModal('boardModal');
        document.getElementById('boardForm').onsubmit = (e) => { e.preventDefault(); this.createBoard(); };

        // Color picker
        document.getElementById('boardColorPicker').addEventListener('click', (e) => {
            const opt = e.target.closest('.color-option');
            if (!opt) return;
            document.querySelectorAll('#boardColorPicker .color-option').forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
        });

        // Kanban
        document.getElementById('backToBoards').onclick = () => this.switchView('boards');
        document.getElementById('addListBtn').onclick = () => this.openModal('listModal');
        document.getElementById('listModalClose').onclick = () => this.closeModal('listModal');
        document.getElementById('listForm').onsubmit = (e) => { e.preventDefault(); this.createList(); };
        document.getElementById('kanbanFilterBtn').onclick = () => {
            document.getElementById('kanbanFilterBar').classList.toggle('hidden');
        };
        ['filterPriority', 'filterAssigned', 'filterLabel'].forEach(id => {
            document.getElementById(id).onchange = () => this.applyFilters();
        });

        // New task modal
        document.getElementById('newTaskModalClose').onclick = () => this.closeModal('newTaskModal');
        document.getElementById('newTaskForm').onsubmit = (e) => { e.preventDefault(); this.createTask(); };

        // Task detail modal
        document.getElementById('taskModalClose').onclick = () => this.closeTaskModal();
        document.getElementById('taskTitleInput').onblur = () => this.saveTaskField('title', document.getElementById('taskTitleInput').value);
        document.getElementById('taskDescription').onblur = () => this.saveTaskField('description', document.getElementById('taskDescription').value);
        document.getElementById('taskStatus').onchange = (e) => this.saveTaskField('status', e.target.value);
        document.getElementById('taskPriority').onchange = (e) => this.saveTaskField('priority', e.target.value);
        document.getElementById('taskAssigned').onchange = (e) => this.saveTaskField('assigned_to', e.target.value);
        document.getElementById('taskDueDate').onchange = (e) => this.saveTaskField('due_date', e.target.value);
        document.getElementById('taskEstHours').onchange = (e) => this.saveTaskField('estimated_hours', e.target.value);
        document.getElementById('taskActHours').onchange = (e) => this.saveTaskField('actual_hours', e.target.value);
        document.getElementById('archiveTaskBtn').onclick = () => this.archiveTask();
        document.getElementById('deleteTaskBtn').onclick = () => this.deleteTask();
        document.getElementById('addCommentBtn').onclick = () => this.addComment();
        document.getElementById('addChecklistBtn').onclick = () => this.addChecklist();
        document.getElementById('checklistInput').onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); this.addChecklist(); } };

        // Member modal
        document.getElementById('addMemberBtn').onclick = () => this.openModal('memberModal');
        document.getElementById('memberModalClose').onclick = () => this.closeModal('memberModal');
        document.getElementById('memberForm').onsubmit = (e) => { e.preventDefault(); this.createMember(); };

        // Notifications
        document.getElementById('notifBell').onclick = () => this.toggleNotifications();
        document.getElementById('markAllRead').onclick = () => this.markAllNotificationsRead();

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.onclick = (e) => { if (e.target === overlay) overlay.classList.add('hidden'); };
        });

        // Global search
        document.getElementById('globalSearch').oninput = (e) => this.globalSearch(e.target.value);

        // Close notification panel on outside click
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('notifPanel');
            const bell = document.getElementById('notifBell');
            if (!panel.classList.contains('hidden') && !panel.contains(e.target) && !bell.contains(e.target)) {
                panel.classList.add('hidden');
            }
        });
    },

    // ==================== Navigation ====================
    switchView(view) {
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        document.querySelectorAll('.nav-item[data-view]').forEach(n => n.classList.remove('active'));
        const nav = document.querySelector(`.nav-item[data-view="${view}"]`);
        if (nav) nav.classList.add('active');

        const titles = { boards: 'لوحات المشاريع', 'my-tasks': 'مهامي', activity: 'سجل النشاط', team: 'الفريق' };

        if (view === 'boards') {
            document.getElementById('boardsView').classList.add('active');
            document.getElementById('pageTitle').textContent = titles.boards;
            this.loadBoards();
        } else if (view === 'kanban') {
            document.getElementById('kanbanView').classList.add('active');
            document.getElementById('pageTitle').textContent = this.currentBoard?.name || '';
        } else if (view === 'my-tasks') {
            document.getElementById('myTasksView').classList.add('active');
            document.getElementById('pageTitle').textContent = titles['my-tasks'];
            this.loadMyTasks();
        } else if (view === 'activity') {
            document.getElementById('activityView').classList.add('active');
            document.getElementById('pageTitle').textContent = titles.activity;
            this.loadActivity();
        } else if (view === 'team') {
            document.getElementById('teamView').classList.add('active');
            document.getElementById('pageTitle').textContent = titles.team;
            this.loadTeam();
        } else if (view === 'email-marketing') {
            document.getElementById('emailMarketingView').classList.add('active');
            document.getElementById('pageTitle').textContent = 'البريد التسويقي';
            this.loadCampaigns();
            this.loadContactLists();
            this.loadSmtp();
        } else if (view === 'whatsapp') {
            document.getElementById('whatsappView').classList.add('active');
            document.getElementById('pageTitle').textContent = 'واتساب';
            this.loadWaCampaigns();
            this.loadWaApiSettings();
        }

        // Close sidebar on mobile
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('show');
    },

    // ==================== Boards ====================
    async loadBoards() {
        try {
            const data = await this.api('/api/boards');
            this.boards = Array.isArray(data) ? data : [];
            this.renderBoards('all');
            this.renderSidebarBoards();
        } catch(e) { console.error('loadBoards error:', e); }
    },

    renderBoards(filter) {
        const grid = document.getElementById('boardsGrid');
        let boards = this.boards;
        if (filter && filter !== 'all') boards = boards.filter(b => b.category === filter);

        if (!boards.length) {
            grid.innerHTML = '<div class="empty-state"><i class="fas fa-th-large"></i><p>لا توجد مشاريع بعد</p></div>';
            return;
        }

        grid.innerHTML = boards.map(b => {
            const pct = b.tasks_count ? Math.round((b.completed_tasks_count / b.tasks_count) * 100) : 0;
            const catClass = `category-${b.category}`;
            const catText = { development: 'تطوير', marketing: 'تسويق', general: 'عام' }[b.category];
            return `
            <div class="board-card" data-id="${b.id}">
                <div class="board-card-header" style="background:${b.color}"></div>
                <div class="board-card-body">
                    <div class="board-card-title">${this.esc(b.name)}</div>
                    <div class="board-card-desc">${this.esc(b.description || '')}</div>
                    <div class="board-card-meta">
                        <span class="board-card-category ${catClass}">${catText}</span>
                        <div class="board-card-stats">
                            <span><i class="fas fa-tasks"></i> ${b.tasks_count}</span>
                            <span><i class="fas fa-check-circle"></i> ${b.completed_tasks_count}</span>
                        </div>
                    </div>
                    <div class="board-card-progress">
                        <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
                    </div>
                </div>
            </div>`;
        }).join('');

        grid.querySelectorAll('.board-card').forEach(card => {
            card.onclick = () => this.openBoard(parseInt(card.dataset.id));
        });
    },

    renderSidebarBoards() {
        const list = document.getElementById('boardsList');
        list.innerHTML = this.boards.map(b => `
            <div class="board-link" data-id="${b.id}">
                <span class="board-dot" style="background:${b.color}"></span>
                <span>${this.esc(b.name)}</span>
            </div>`).join('');
        list.querySelectorAll('.board-link').forEach(link => {
            link.onclick = () => this.openBoard(parseInt(link.dataset.id));
        });
    },

    async openBoard(id) {
        const board = await this.api(`/api/boards/${id}`);
        this.currentBoard = board;
        document.getElementById('kanbanTitle').textContent = board.name;
        const catText = { development: 'تطوير', marketing: 'تسويق', general: 'عام' }[board.category];
        const badge = document.getElementById('kanbanCategory');
        badge.textContent = catText;
        badge.className = `board-category-badge category-${board.category}`;

        // Members
        const membersEl = document.getElementById('kanbanMembers');
        membersEl.innerHTML = board.members.map(m =>
            `<div class="kanban-member" style="background:${m.avatar_color}" title="${this.esc(m.full_name)}">${m.full_name.charAt(0)}</div>`
        ).join('');

        // Populate filter dropdowns
        const filterAssigned = document.getElementById('filterAssigned');
        filterAssigned.innerHTML = '<option value="">كل الأعضاء</option>' +
            board.members.map(m => `<option value="${m.id}">${this.esc(m.full_name)}</option>`).join('');
        const filterLabel = document.getElementById('filterLabel');
        filterLabel.innerHTML = '<option value="">كل التصنيفات</option>' +
            board.labels.map(l => `<option value="${l.id}">${this.esc(l.name)}</option>`).join('');

        this.renderKanban(board);
        this.switchView('kanban');

        // Highlight in sidebar
        document.querySelectorAll('.board-link').forEach(l => l.classList.remove('active'));
        const active = document.querySelector(`.board-link[data-id="${id}"]`);
        if (active) active.classList.add('active');
    },

    renderKanban(board) {
        const container = document.getElementById('kanbanBoard');
        container.innerHTML = board.lists.map(list => `
            <div class="kanban-list" data-list-id="${list.id}">
                <div class="kanban-list-header">
                    <div class="kanban-list-title">
                        <span>${this.esc(list.name)}</span>
                        <span class="kanban-list-count">${list.tasks.length}</span>
                    </div>
                    <div class="kanban-list-actions">
                        <button class="btn-icon delete-list-btn" data-id="${list.id}" title="حذف القائمة"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="kanban-list-body" data-list-id="${list.id}">
                    ${list.tasks.map(t => this.renderTaskCard(t)).join('')}
                </div>
                <div class="kanban-list-footer">
                    <button class="add-task-btn" data-list-id="${list.id}" data-board-id="${board.id}">
                        <i class="fas fa-plus"></i> إضافة مهمة
                    </button>
                </div>
            </div>
        `).join('');

        // Task card clicks
        container.querySelectorAll('.task-card').forEach(card => {
            card.onclick = (e) => { if (!e.target.closest('.sortable-handle')) this.openTaskModal(parseInt(card.dataset.id)); };
        });

        // Add task buttons
        container.querySelectorAll('.add-task-btn').forEach(btn => {
            btn.onclick = () => this.openNewTaskModal(parseInt(btn.dataset.listId), parseInt(btn.dataset.boardId));
        });

        // Delete list buttons
        container.querySelectorAll('.delete-list-btn').forEach(btn => {
            btn.onclick = async (e) => {
                e.stopPropagation();
                if (confirm('هل أنت متأكد من حذف هذه القائمة وجميع مهامها؟')) {
                    await this.api(`/api/lists/${btn.dataset.id}`, 'DELETE');
                    this.openBoard(this.currentBoard.id);
                    this.toast('تم حذف القائمة', 'success');
                }
            };
        });

        // Drag & Drop
        container.querySelectorAll('.kanban-list-body').forEach(listBody => {
            new Sortable(listBody, {
                group: 'tasks', animation: 200, ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag', chosenClass: 'sortable-chosen',
                handle: '.task-card', delay: 100, delayOnTouchOnly: true,
                onEnd: (evt) => this.onTaskDragEnd(evt),
            });
        });
    },

    renderTaskCard(task) {
        const labels = (task.labels || []).map(l =>
            `<div class="task-label-dot" style="background:${l.color}" title="${this.esc(l.name)}"></div>`
        ).join('');

        const priorityIcons = { urgent: 'fa-exclamation-circle', high: 'fa-arrow-up', medium: 'fa-minus', low: 'fa-arrow-down' };
        const priorityTexts = { urgent: 'عاجل', high: 'مرتفع', medium: 'متوسط', low: 'منخفض' };

        let dueBadge = '';
        if (task.due_date) {
            const due = new Date(task.due_date);
            const now = new Date();
            const diff = Math.ceil((due - now) / (1000 * 60 * 60 * 24));
            let cls = '';
            if (diff < 0) cls = 'due-overdue';
            else if (diff <= 2) cls = 'due-soon';
            const dateStr = due.toLocaleDateString('ar-EG', { month: 'short', day: 'numeric' });
            dueBadge = `<span class="task-badge ${cls}"><i class="fas fa-clock"></i> ${dateStr}</span>`;
        }

        let checkBadge = '';
        if (task.checklist_total > 0) {
            checkBadge = `<span class="task-badge"><i class="fas fa-check-square"></i> ${task.checklist_done}/${task.checklist_total}</span>`;
        }

        let commentBadge = '';
        if (task.comment_count > 0) {
            commentBadge = `<span class="task-badge"><i class="fas fa-comment"></i> ${task.comment_count}</span>`;
        }

        const assignee = task.assignee ?
            `<div class="task-card-avatar" style="background:${task.assignee.avatar_color}" title="${this.esc(task.assignee.full_name)}">${task.assignee.full_name.charAt(0)}</div>` : '';

        return `
        <div class="task-card" data-id="${task.id}">
            ${labels ? `<div class="task-card-labels">${labels}</div>` : ''}
            <div class="task-card-title">${this.esc(task.title)}</div>
            <div class="task-card-meta">
                <div class="task-card-badges">
                    <span class="task-badge priority-${task.priority}"><i class="fas ${priorityIcons[task.priority]}"></i> ${priorityTexts[task.priority]}</span>
                    ${dueBadge}${checkBadge}${commentBadge}
                </div>
                ${assignee}
            </div>
        </div>`;
    },

    async onTaskDragEnd(evt) {
        const taskId = parseInt(evt.item.dataset.id);
        const newListId = parseInt(evt.to.dataset.listId);
        const positions = [];
        evt.to.querySelectorAll('.task-card').forEach((card, i) => {
            positions.push({ id: parseInt(card.dataset.id), position: i });
        });
        await this.api(`/api/tasks/${taskId}/move`, 'POST', {
            list_id: newListId, position: evt.newIndex, positions
        });
    },

    applyFilters() {
        const priority = document.getElementById('filterPriority').value;
        const assigned = document.getElementById('filterAssigned').value;
        const label = document.getElementById('filterLabel').value;
        document.querySelectorAll('.task-card').forEach(card => {
            const id = parseInt(card.dataset.id);
            let task = null;
            for (const list of this.currentBoard.lists) {
                task = list.tasks.find(t => t.id === id);
                if (task) break;
            }
            if (!task) return;
            let show = true;
            if (priority && task.priority !== priority) show = false;
            if (assigned && task.assigned_to != assigned) show = false;
            if (label && !(task.labels || []).some(l => l.id == label)) show = false;
            card.style.display = show ? '' : 'none';
        });
    },

    // ==================== Board CRUD ====================
    openBoardModal() {
        document.getElementById('boardForm').reset();
        document.querySelectorAll('#boardColorPicker .color-option').forEach(o => o.classList.remove('selected'));
        document.querySelector('#boardColorPicker .color-option').classList.add('selected');
        this.openModal('boardModal');
    },

    async createBoard() {
        const color = document.querySelector('#boardColorPicker .color-option.selected')?.dataset.color || '#6366f1';
        const data = {
            name: document.getElementById('boardName').value,
            description: document.getElementById('boardDesc').value,
            category: document.getElementById('boardCategory').value,
            color
        };
        await this.api('/api/boards', 'POST', data);
        this.closeModal('boardModal');
        this.loadBoards();
        this.toast('تم إنشاء المشروع بنجاح', 'success');
    },

    // ==================== List CRUD ====================
    async createList() {
        await this.api('/api/lists', 'POST', {
            board_id: this.currentBoard.id,
            name: document.getElementById('listName').value,
        });
        this.closeModal('listModal');
        document.getElementById('listForm').reset();
        this.openBoard(this.currentBoard.id);
        this.toast('تم إنشاء القائمة', 'success');
    },

    // ==================== Task CRUD ====================
    openNewTaskModal(listId, boardId) {
        document.getElementById('newTaskForm').reset();
        document.getElementById('newTaskListId').value = listId;
        document.getElementById('newTaskBoardId').value = boardId;
        this.populateAssigneeSelect('newTaskAssigned');
        this.openModal('newTaskModal');
        document.getElementById('newTaskTitle').focus();
    },

    async createTask() {
        const data = {
            list_id: document.getElementById('newTaskListId').value,
            board_id: document.getElementById('newTaskBoardId').value,
            title: document.getElementById('newTaskTitle').value,
            description: document.getElementById('newTaskDesc').value,
            priority: document.getElementById('newTaskPriority').value,
            assigned_to: document.getElementById('newTaskAssigned').value,
            due_date: document.getElementById('newTaskDueDate').value,
        };
        await this.api('/api/tasks', 'POST', data);
        this.closeModal('newTaskModal');
        this.openBoard(this.currentBoard.id);
        this.toast('تم إضافة المهمة', 'success');
    },

    async openTaskModal(taskId) {
        const task = await this.api(`/api/tasks/${taskId}`);
        this.currentTask = task;

        document.getElementById('taskTitleInput').value = task.title;
        document.getElementById('taskDescription').value = task.description || '';
        document.getElementById('taskStatus').value = task.status;
        document.getElementById('taskPriority').value = task.priority;
        document.getElementById('taskDueDate').value = task.due_date || '';
        document.getElementById('taskEstHours').value = task.estimated_hours || '';
        document.getElementById('taskActHours').value = task.actual_hours || '';

        this.populateAssigneeSelect('taskAssigned', task.assigned_to);

        // Labels
        const labelsEl = document.getElementById('taskLabels');
        const boardLabels = this.currentBoard?.labels || [];
        const taskLabelIds = (task.labels || []).map(l => l.id);
        labelsEl.innerHTML = boardLabels.map(l => {
            const active = taskLabelIds.includes(l.id);
            return `<span class="task-label-tag ${active ? '' : 'inactive'}" data-id="${l.id}" style="background:${l.color}">${this.esc(l.name)}</span>`;
        }).join('');
        labelsEl.querySelectorAll('.task-label-tag').forEach(tag => {
            tag.onclick = () => this.toggleLabel(parseInt(tag.dataset.id));
        });

        // Checklists
        this.renderChecklists(task.checklists || []);

        // Comments
        this.renderComments(task.comments || []);

        // Activity
        this.renderTaskActivity(task.activities || []);

        this.openModal('taskModal');
    },

    async saveTaskField(field, value) {
        if (!this.currentTask) return;
        await this.api(`/api/tasks/${this.currentTask.id}`, 'PUT', { [field]: value });
    },

    async toggleLabel(labelId) {
        if (!this.currentTask) return;
        const labels = this.currentTask.labels || [];
        let ids = labels.map(l => l.id);
        if (ids.includes(labelId)) ids = ids.filter(id => id !== labelId);
        else ids.push(labelId);
        await this.api(`/api/tasks/${this.currentTask.id}`, 'PUT', { label_ids: ids });
        this.openTaskModal(this.currentTask.id);
    },

    closeTaskModal() {
        this.closeModal('taskModal');
        if (this.currentBoard) this.openBoard(this.currentBoard.id);
    },

    async archiveTask() {
        if (!this.currentTask || !confirm('هل تريد أرشفة هذه المهمة؟')) return;
        await this.api(`/api/tasks/${this.currentTask.id}/archive`, 'POST');
        this.closeTaskModal();
        this.toast('تم أرشفة المهمة', 'success');
    },

    async deleteTask() {
        if (!this.currentTask || !confirm('هل تريد حذف هذه المهمة نهائياً؟')) return;
        await this.api(`/api/tasks/${this.currentTask.id}`, 'DELETE');
        this.closeTaskModal();
        this.toast('تم حذف المهمة', 'success');
    },

    // ==================== Checklists ====================
    renderChecklists(items) {
        const total = items.length;
        const done = items.filter(i => i.is_completed).length;
        const pct = total ? Math.round((done / total) * 100) : 0;
        document.getElementById('checklistProgress').innerHTML = total ?
            `<div class="checklist-progress-fill" style="width:${pct}%"></div>` : '';

        document.getElementById('taskChecklist').innerHTML = items.map(item => `
            <div class="checklist-item">
                <input type="checkbox" ${item.is_completed ? 'checked' : ''} data-id="${item.id}" onchange="App.toggleChecklist(${item.id})">
                <span class="${item.is_completed ? 'completed' : ''}">${this.esc(item.title)}</span>
                <button class="btn-icon" onclick="App.deleteChecklist(${item.id})"><i class="fas fa-times"></i></button>
            </div>
        `).join('');
    },

    async addChecklist() {
        const input = document.getElementById('checklistInput');
        if (!input.value.trim() || !this.currentTask) return;
        await this.api('/api/checklists', 'POST', { task_id: this.currentTask.id, title: input.value.trim() });
        input.value = '';
        this.openTaskModal(this.currentTask.id);
    },

    async toggleChecklist(id) {
        await this.api(`/api/checklists/${id}/toggle`, 'POST');
        this.openTaskModal(this.currentTask.id);
    },

    async deleteChecklist(id) {
        await this.api(`/api/checklists/${id}`, 'DELETE');
        this.openTaskModal(this.currentTask.id);
    },

    // ==================== Comments ====================
    renderComments(comments) {
        document.getElementById('taskComments').innerHTML = comments.map(c => `
            <div class="comment-item">
                <div class="comment-avatar" style="background:${c.user?.avatar_color || '#6366f1'}">${(c.user?.full_name || '?').charAt(0)}</div>
                <div class="comment-content">
                    <div class="comment-header">
                        <span class="comment-author">${this.esc(c.user?.full_name || '')}</span>
                        <span class="comment-time">${this.timeAgo(c.created_at)}</span>
                        ${c.user_id === this.user.id ? `<button class="comment-delete" onclick="App.deleteComment(${c.id})">حذف</button>` : ''}
                    </div>
                    <div class="comment-text">${this.esc(c.content)}</div>
                </div>
            </div>
        `).join('') || '<p style="color:var(--text-muted);font-size:13px;padding:8px 0;">لا توجد تعليقات بعد</p>';
    },

    async addComment() {
        const input = document.getElementById('commentInput');
        if (!input.value.trim() || !this.currentTask) return;
        await this.api('/api/comments', 'POST', { task_id: this.currentTask.id, content: input.value.trim() });
        input.value = '';
        this.openTaskModal(this.currentTask.id);
    },

    async deleteComment(id) {
        await this.api(`/api/comments/${id}`, 'DELETE');
        this.openTaskModal(this.currentTask.id);
    },

    // ==================== Task Activity ====================
    renderTaskActivity(activities) {
        const actionTexts = {
            task_created: 'أنشأ المهمة', task_updated: 'حدث المهمة', task_moved: 'نقل المهمة',
            comment_added: 'أضاف تعليق', board_created: 'أنشأ المشروع',
        };
        document.getElementById('taskActivity').innerHTML = activities.map(a => `
            <div class="task-activity-item">
                <i class="fas fa-circle"></i>
                <span><strong>${this.esc(a.user?.full_name || '')}</strong> ${actionTexts[a.action] || a.action} ${a.details ? `- ${this.esc(a.details)}` : ''} <em>${this.timeAgo(a.created_at)}</em></span>
            </div>
        `).join('') || '<p style="color:var(--text-muted);font-size:13px;">لا يوجد نشاط</p>';
    },

    // ==================== My Tasks ====================
    async loadMyTasks() {
        const raw = await this.api('/api/tasks/my');
        const tasks = Array.isArray(raw) ? raw : [];
        const content = document.getElementById('myTasksContent');
        const stats = document.getElementById('myTasksStats');

        const total = tasks.length;
        const completed = tasks.filter(t => t.status === 'completed').length;
        const urgent = tasks.filter(t => t.priority === 'urgent' || t.priority === 'high').length;
        stats.innerHTML = `
            <div class="stat-item"><span class="stat-number">${total}</span> إجمالي</div>
            <div class="stat-item"><span class="stat-number" style="color:var(--success)">${completed}</span> مكتمل</div>
            <div class="stat-item"><span class="stat-number" style="color:var(--danger)">${urgent}</span> عاجل</div>
        `;

        if (!tasks.length) {
            content.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i><p>لا توجد مهام مسندة إليك</p></div>';
            return;
        }

        const priorityColors = { urgent: 'var(--danger)', high: 'var(--warning)', medium: 'var(--info)', low: 'var(--success)' };

        content.innerHTML = tasks.map(t => {
            let dueStr = '';
            if (t.due_date) {
                const d = new Date(t.due_date);
                dueStr = d.toLocaleDateString('ar-EG', { month: 'short', day: 'numeric' });
            }
            return `
            <div class="my-task-item" data-id="${t.id}" data-board="${t.board_id}">
                <div class="my-task-priority-dot" style="background:${priorityColors[t.priority]}"></div>
                <div class="my-task-info">
                    <div class="my-task-title">${this.esc(t.title)}</div>
                    <div class="my-task-sub">
                        <span style="color:${t.board?.color || '#6366f1'}"><i class="fas fa-th-large"></i> ${this.esc(t.board?.name || '')}</span>
                        <span><i class="fas fa-list"></i> ${this.esc(t.list?.name || '')}</span>
                    </div>
                </div>
                ${dueStr ? `<div class="my-task-due"><i class="fas fa-clock"></i> ${dueStr}</div>` : ''}
            </div>`;
        }).join('');

        content.querySelectorAll('.my-task-item').forEach(item => {
            item.onclick = async () => {
                const boardId = parseInt(item.dataset.board);
                if (!this.currentBoard || this.currentBoard.id !== boardId) {
                    this.currentBoard = await this.api(`/api/boards/${boardId}`);
                }
                this.openTaskModal(parseInt(item.dataset.id));
            };
        });
    },

    // ==================== Activity ====================
    async loadActivity() {
        const activities = await this.api('/api/activity');
        const list = document.getElementById('activityList');
        const actionTexts = {
            task_created: 'أنشأ مهمة', task_updated: 'حدث مهمة', task_moved: 'نقل مهمة',
            comment_added: 'أضاف تعليق', board_created: 'أنشأ مشروع',
        };
        list.innerHTML = activities.map(a => `
            <div class="activity-item">
                <div class="activity-avatar" style="background:${a.user?.avatar_color || '#6366f1'}">${(a.user?.full_name || '?').charAt(0)}</div>
                <div>
                    <div class="activity-text"><strong>${this.esc(a.user?.full_name || '')}</strong> ${actionTexts[a.action] || a.action} ${a.details ? `<em>${this.esc(a.details)}</em>` : ''} ${a.board ? `في <strong>${this.esc(a.board.name)}</strong>` : ''}</div>
                    <div class="activity-time">${this.timeAgo(a.created_at)}</div>
                </div>
            </div>
        `).join('') || '<div class="empty-state"><i class="fas fa-history"></i><p>لا يوجد نشاط بعد</p></div>';
    },

    // ==================== Team ====================
    async loadTeam() {
        const users = await this.api('/api/users');
        const grid = document.getElementById('teamGrid');
        const roles = { admin: 'مسؤول', manager: 'مدير مشروع', developer: 'مطور', marketer: 'مسوق' };
        grid.innerHTML = users.map(u => `
            <div class="team-card">
                <div class="team-card-avatar" style="background:${u.avatar_color}">${u.full_name.charAt(0)}</div>
                <div class="team-card-name">${this.esc(u.full_name)}</div>
                <div class="team-card-role">${roles[u.role] || u.role}</div>
                <div class="team-card-stats">
                    <span><i class="fas fa-envelope"></i> ${this.esc(u.email || '-')}</span>
                </div>
            </div>
        `).join('');
    },

    async createMember() {
        const data = {
            username: document.getElementById('memberUsername').value,
            full_name: document.getElementById('memberFullName').value,
            email: document.getElementById('memberEmail').value,
            password: document.getElementById('memberPassword').value,
            role: document.getElementById('memberRole').value,
        };
        const res = await this.api('/api/users', 'POST', data);
        if (res.error) { this.toast(res.error, 'error'); return; }
        this.closeModal('memberModal');
        this.loadTeam();
        await this.loadUsers();
        this.toast('تم إضافة العضو بنجاح', 'success');
    },

    // ==================== Users ====================
    async loadUsers() {
        try {
            const data = await this.api('/api/users');
            this.users = Array.isArray(data) ? data : [];
        } catch(e) { this.users = []; }
    },

    populateAssigneeSelect(selectId, selectedId = null) {
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">-- غير محدد --</option>' +
            this.users.map(u => `<option value="${u.id}" ${u.id == selectedId ? 'selected' : ''}>${this.esc(u.full_name)}</option>`).join('');
    },

    // ==================== Notifications ====================
    async pollNotifications() {
        try {
            const data = await this.api('/api/notifications/unread-count');
            const badge = document.getElementById('notifBadge');
            if (data.count > 0) { badge.textContent = data.count; badge.classList.remove('hidden'); }
            else { badge.classList.add('hidden'); }
        } catch (e) {}
        setTimeout(() => this.pollNotifications(), 30000);
    },

    async toggleNotifications() {
        const panel = document.getElementById('notifPanel');
        if (!panel.classList.contains('hidden')) { panel.classList.add('hidden'); return; }
        const notifs = await this.api('/api/notifications');
        const list = document.getElementById('notifList');
        if (!notifs.length) {
            list.innerHTML = '<div class="notif-empty">لا توجد إشعارات</div>';
        } else {
            list.innerHTML = notifs.map(n => `
                <div class="notif-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}">
                    <div class="notif-item-title">${this.esc(n.title)}</div>
                    <div class="notif-item-msg">${this.esc(n.message || '')}</div>
                    <div class="notif-item-time">${this.timeAgo(n.created_at)}</div>
                </div>
            `).join('');
        }
        panel.classList.remove('hidden');
    },

    async markAllNotificationsRead() {
        await this.api('/api/notifications/mark-read', 'POST');
        document.getElementById('notifBadge').classList.add('hidden');
        document.querySelectorAll('.notif-item.unread').forEach(i => i.classList.remove('unread'));
        this.toast('تم تحديد الكل كمقروء', 'info');
    },

    // ==================== Search ====================
    globalSearch(query) {
        if (!query.trim()) {
            document.querySelectorAll('.task-card').forEach(c => c.style.display = '');
            document.querySelectorAll('.board-card').forEach(c => c.style.display = '');
            return;
        }
        const q = query.toLowerCase();
        document.querySelectorAll('.task-card').forEach(card => {
            const title = card.querySelector('.task-card-title')?.textContent?.toLowerCase() || '';
            card.style.display = title.includes(q) ? '' : 'none';
        });
        document.querySelectorAll('.board-card').forEach(card => {
            const title = card.querySelector('.board-card-title')?.textContent?.toLowerCase() || '';
            card.style.display = title.includes(q) ? '' : 'none';
        });
    },

    // ==================== Helpers ====================
    openModal(id) { document.getElementById(id).classList.remove('hidden'); },
    closeModal(id) { document.getElementById(id).classList.add('hidden'); },

    toast(msg, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
        toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> ${msg}`;
        container.appendChild(toast);
        setTimeout(() => { toast.classList.add('toast-out'); setTimeout(() => toast.remove(), 300); }, 3000);
    },

    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    timeAgo(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'الآن';
        if (diff < 3600) return `منذ ${Math.floor(diff / 60)} دقيقة`;
        if (diff < 86400) return `منذ ${Math.floor(diff / 3600)} ساعة`;
        if (diff < 604800) return `منذ ${Math.floor(diff / 86400)} يوم`;
        return date.toLocaleDateString('ar-EG');
    },

    // Upload helper
    async upload(url, formData) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
            body: formData,
        });
        return res.json();
    },

    // ================================================================
    //                    EMAIL MARKETING
    // ================================================================

    currentContactList: null,
    currentCampaign: null,
    sendingInterval: null,

    setupEmailEvents() {
        // Tab switching
        document.querySelectorAll('.service-tab[data-etab]').forEach(tab => {
            tab.onclick = () => {
                document.querySelectorAll('.service-tab[data-etab]').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                document.querySelectorAll('#emailMarketingView .service-panel').forEach(p => p.classList.remove('active'));
                document.getElementById({
                    'campaigns': 'emailCampaignsPanel',
                    'contacts': 'emailContactsPanel',
                    'smtp': 'emailSmtpPanel',
                }[tab.dataset.etab]).classList.add('active');
            };
        });

        // SMTP
        document.getElementById('newSmtpBtn').onclick = () => { document.getElementById('smtpForm').reset(); document.getElementById('smtpId').value = ''; this.openModal('smtpModal'); };
        document.getElementById('smtpForm').onsubmit = (e) => { e.preventDefault(); this.saveSmtp(); };

        // Contact Lists
        document.getElementById('newContactListBtn').onclick = () => { document.getElementById('contactListForm').reset(); this.openModal('contactListModal'); };
        document.getElementById('contactListForm').onsubmit = (e) => { e.preventDefault(); this.createContactList(); };
        document.getElementById('importContactsBtn').onclick = () => this.importContacts();
        document.getElementById('addManualContactBtn').onclick = () => this.addManualContact();

        // Campaigns
        document.getElementById('newCampaignBtn').onclick = () => this.openCampaignModal();
        document.getElementById('saveCampaignBtn').onclick = () => this.saveCampaign();
        document.getElementById('sendCampaignBtn').onclick = () => this.startSendCampaign();
        document.getElementById('loadTemplateBtn').onclick = () => this.loadTemplates();
        document.getElementById('previewEmailBtn').onclick = () => this.previewEmail();
        document.getElementById('closeSendingBtn').onclick = () => { this.closeModal('sendingModal'); if (this.sendingInterval) clearInterval(this.sendingInterval); };
        document.getElementById('pauseSendingBtn').onclick = () => this.pauseSending();
    },

    // ----- SMTP -----
    async loadSmtp() {
        const list = await this.api('/api/smtp');
        document.getElementById('smtpListContainer').innerHTML = list.length ? list.map(s => `
            <div class="smtp-card">
                <div class="smtp-info">
                    <div class="smtp-name">${this.esc(s.name)}</div>
                    <div class="smtp-details">
                        <span><i class="fas fa-server"></i> ${this.esc(s.host)}:${s.port}</span>
                        <span><i class="fas fa-envelope"></i> ${this.esc(s.from_email)}</span>
                        <span><i class="fas fa-user"></i> ${this.esc(s.from_name)}</span>
                    </div>
                </div>
                <div class="smtp-actions">
                    <button class="btn btn-sm btn-outline" onclick="App.testSmtp(${s.id})"><i class="fas fa-vial"></i></button>
                    <button class="btn btn-sm btn-outline btn-danger" onclick="App.deleteSmtp(${s.id})"><i class="fas fa-trash"></i></button>
                </div>
            </div>`).join('') : '<div class="empty-state"><i class="fas fa-server"></i><p>لم تقم بإضافة إعدادات SMTP بعد</p></div>';
    },

    async saveSmtp() {
        const data = {
            name: document.getElementById('smtpName').value,
            host: document.getElementById('smtpHost').value,
            port: parseInt(document.getElementById('smtpPort').value),
            encryption: document.getElementById('smtpEncryption').value,
            username: document.getElementById('smtpUsername').value,
            password: document.getElementById('smtpPassword').value,
            from_email: document.getElementById('smtpFromEmail').value,
            from_name: document.getElementById('smtpFromName').value,
        };
        const id = document.getElementById('smtpId').value;
        if (id) { await this.api(`/api/smtp/${id}`, 'PUT', data); }
        else { await this.api('/api/smtp', 'POST', data); }
        this.closeModal('smtpModal');
        this.loadSmtp();
        this.toast('تم حفظ إعدادات SMTP', 'success');
    },

    async testSmtp(id) {
        this.toast('جاري اختبار الاتصال...', 'info');
        const res = await this.api(`/api/smtp/${id}/test`, 'POST');
        this.toast(res.message, res.success ? 'success' : 'error');
    },

    async deleteSmtp(id) {
        if (!confirm('هل تريد حذف هذا الإعداد؟')) return;
        await this.api(`/api/smtp/${id}`, 'DELETE');
        this.loadSmtp();
        this.toast('تم الحذف', 'success');
    },

    // ----- Contact Lists -----
    async loadContactLists() {
        const lists = await this.api('/api/contact-lists');
        document.getElementById('contactListsContainer').innerHTML = lists.length ? lists.map(l => `
            <div class="cl-card" onclick="App.openContactList(${l.id})">
                <div class="cl-info">
                    <div class="cl-name">${this.esc(l.name)}</div>
                    <div class="cl-count">${l.description || ''}</div>
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                    <span class="cl-badge">${l.contacts_count || 0} جهة</span>
                    <button class="btn-icon" onclick="event.stopPropagation();App.deleteContactList(${l.id})"><i class="fas fa-trash" style="color:var(--danger)"></i></button>
                </div>
            </div>`).join('') : '<div class="empty-state"><i class="fas fa-address-book"></i><p>لم تقم بإنشاء قوائم بعد</p></div>';
    },

    async createContactList() {
        await this.api('/api/contact-lists', 'POST', { name: document.getElementById('clName').value, description: document.getElementById('clDesc').value });
        this.closeModal('contactListModal');
        this.loadContactLists();
        this.toast('تم إنشاء القائمة', 'success');
    },

    async deleteContactList(id) {
        if (!confirm('حذف القائمة وجميع جهات الاتصال؟')) return;
        await this.api(`/api/contact-lists/${id}`, 'DELETE');
        this.loadContactLists();
    },

    async openContactList(id) {
        const list = await this.api(`/api/contact-lists/${id}`);
        this.currentContactList = list;
        document.getElementById('contactDetailTitle').textContent = list.name + ' (' + (list.contacts?.length || 0) + ' جهة)';
        const contacts = list.contacts || [];
        document.getElementById('contactsTable').innerHTML = contacts.length ? `
            <table class="contacts-tbl">
                <thead><tr><th>الاسم</th><th>البريد الإلكتروني</th><th>الهاتف</th><th></th></tr></thead>
                <tbody>${contacts.map(c => `
                    <tr><td>${this.esc(c.name || '-')}</td><td style="direction:ltr">${this.esc(c.email)}</td><td style="direction:ltr">${this.esc(c.phone || '-')}</td>
                    <td><button class="btn-icon" onclick="App.removeContact(${c.id})"><i class="fas fa-times" style="color:var(--danger);font-size:11px"></i></button></td></tr>
                `).join('')}</tbody>
            </table>` : '<p style="text-align:center;color:var(--text-muted);padding:20px">لا توجد جهات اتصال</p>';
        this.openModal('contactDetailModal');
    },

    async importContacts() {
        const file = document.getElementById('contactFileInput').files[0];
        if (!file || !this.currentContactList) return;
        const fd = new FormData();
        fd.append('file', file);
        const res = await this.upload(`/api/contact-lists/${this.currentContactList.id}/import`, fd);
        this.toast(res.message || `تم استيراد ${res.imported} جهة`, res.success ? 'success' : 'error');
        this.openContactList(this.currentContactList.id);
        this.loadContactLists();
    },

    async addManualContact() {
        const email = document.getElementById('manualEmail').value;
        if (!email || !this.currentContactList) return;
        await this.api(`/api/contact-lists/${this.currentContactList.id}/add`, 'POST', {
            email, name: document.getElementById('manualName').value, phone: document.getElementById('manualPhone').value
        });
        document.getElementById('manualEmail').value = '';
        document.getElementById('manualName').value = '';
        document.getElementById('manualPhone').value = '';
        this.openContactList(this.currentContactList.id);
        this.loadContactLists();
    },

    async removeContact(id) {
        await this.api(`/api/contacts/${id}`, 'DELETE');
        if (this.currentContactList) this.openContactList(this.currentContactList.id);
        this.loadContactLists();
    },

    // ----- Email Campaigns -----
    async loadCampaigns() {
        const campaigns = await this.api('/api/email-campaigns');
        const statusText = { draft: 'مسودة', sending: 'جاري الإرسال', sent: 'تم الإرسال', paused: 'متوقف', failed: 'فشل' };
        document.getElementById('campaignsList').innerHTML = campaigns.length ? campaigns.map(c => {
            const pct = c.total_recipients ? Math.round(((c.sent_count + c.failed_count) / c.total_recipients) * 100) : 0;
            return `
            <div class="campaign-card">
                <div class="campaign-card-header">
                    <div class="campaign-card-title">${this.esc(c.name)}</div>
                    <span class="campaign-status status-${c.status}">${statusText[c.status]}</span>
                </div>
                <div class="campaign-card-meta">
                    <span><i class="fas fa-envelope"></i> ${this.esc(c.subject)}</span>
                    <span><i class="fas fa-address-book"></i> ${this.esc(c.contact_list?.name || '-')}</span>
                    <span><i class="fas fa-server"></i> ${this.esc(c.smtp_setting?.from_email || '-')}</span>
                    ${c.total_recipients ? `<span><i class="fas fa-paper-plane"></i> ${c.sent_count}/${c.total_recipients}</span>` : ''}
                </div>
                ${c.total_recipients ? `<div class="campaign-progress"><div class="progress-bar"><div class="progress-fill" style="width:${pct}%;background:${c.status==='sent'?'var(--success)':'var(--accent)'}"></div></div></div>` : ''}
                <div class="campaign-card-actions">
                    ${c.status === 'draft' ? `<button class="btn btn-sm btn-primary" onclick="App.editCampaign(${c.id})"><i class="fas fa-edit"></i> تعديل</button>
                    <button class="btn btn-sm" style="background:var(--success);color:#fff" onclick="App.startSendCampaignById(${c.id})"><i class="fas fa-paper-plane"></i> إرسال</button>` : ''}
                    ${c.status === 'sending' ? `<button class="btn btn-sm btn-outline" onclick="App.resumeSending(${c.id})"><i class="fas fa-chart-line"></i> متابعة</button>` : ''}
                    ${c.status === 'paused' ? `<button class="btn btn-sm" style="background:var(--success);color:#fff" onclick="App.resumeCampaign(${c.id})"><i class="fas fa-play"></i> استئناف</button>` : ''}
                    <button class="btn btn-sm btn-outline btn-danger" onclick="App.deleteCampaign(${c.id})"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
        }).join('') : '<div class="empty-state"><i class="fas fa-paper-plane"></i><p>لم تقم بإنشاء حملات بعد</p></div>';
    },

    async openCampaignModal(campaign = null) {
        this.currentCampaign = campaign;
        document.getElementById('campName').value = campaign?.name || '';
        document.getElementById('campSubject').value = campaign?.subject || '';
        document.getElementById('campContent').value = campaign?.html_content || '';
        document.getElementById('campDelay').value = campaign?.delay_seconds || 2;

        // Load SMTP options
        const smtps = await this.api('/api/smtp');
        document.getElementById('campSmtp').innerHTML = '<option value="">-- اختر SMTP --</option>' +
            smtps.map(s => `<option value="${s.id}" ${campaign?.smtp_setting_id==s.id?'selected':''}>${this.esc(s.name)} (${this.esc(s.from_email)})</option>`).join('');

        // Load contact lists
        const lists = await this.api('/api/contact-lists');
        document.getElementById('campList').innerHTML = '<option value="">-- اختر قائمة --</option>' +
            lists.map(l => `<option value="${l.id}" ${campaign?.list_id==l.id?'selected':''}>${this.esc(l.name)} (${l.contacts_count || 0})</option>`).join('');

        document.getElementById('campaignModalTitle').textContent = campaign ? 'تعديل الحملة' : 'حملة بريد جديدة';
        this.openModal('campaignModal');
    },

    async editCampaign(id) {
        const c = await this.api(`/api/email-campaigns/${id}`);
        this.openCampaignModal(c);
    },

    async saveCampaign() {
        const data = {
            name: document.getElementById('campName').value,
            subject: document.getElementById('campSubject').value,
            html_content: document.getElementById('campContent').value,
            smtp_setting_id: document.getElementById('campSmtp').value,
            list_id: document.getElementById('campList').value,
            delay_seconds: parseInt(document.getElementById('campDelay').value),
        };
        if (!data.name || !data.subject || !data.html_content || !data.smtp_setting_id || !data.list_id) {
            this.toast('يرجى ملء جميع الحقول المطلوبة', 'error'); return;
        }
        if (this.currentCampaign) {
            await this.api(`/api/email-campaigns/${this.currentCampaign.id}`, 'PUT', data);
        } else {
            const res = await this.api('/api/email-campaigns', 'POST', data);
            this.currentCampaign = res;
        }
        this.closeModal('campaignModal');
        this.loadCampaigns();
        this.toast('تم حفظ الحملة', 'success');
    },

    async startSendCampaign() {
        await this.saveCampaign();
        if (!this.currentCampaign) return;
        await this.startSendCampaignById(this.currentCampaign.id);
    },

    async startSendCampaignById(id) {
        const res = await this.api(`/api/email-campaigns/${id}/send`, 'POST');
        if (res.error) { this.toast(res.error, 'error'); return; }
        this.currentCampaign = { id };
        this.openSendingProgress(id, 'email');
    },

    openSendingProgress(id, type = 'email') {
        document.getElementById('sendingTitle').textContent = 'جاري الإرسال...';
        document.getElementById('sendingPct').textContent = '0%';
        document.getElementById('sendingSent').textContent = '0';
        document.getElementById('sendingFailed').textContent = '0';
        document.getElementById('sendingRemaining').textContent = '...';
        document.getElementById('sendingCircle').style.strokeDashoffset = '339.292';
        this.openModal('sendingModal');

        const url = type === 'email' ? `/api/email-campaigns/${id}/process` : `/api/whatsapp/campaigns/${id}/process`;
        const pauseUrl = type === 'email' ? `/api/email-campaigns/${id}/pause` : `/api/whatsapp/campaigns/${id}/pause`;

        document.getElementById('pauseSendingBtn').onclick = async () => {
            await this.api(pauseUrl, 'POST');
            if (this.sendingInterval) clearInterval(this.sendingInterval);
            this.toast('تم إيقاف الإرسال مؤقتاً', 'info');
            if (type === 'email') this.loadCampaigns(); else this.loadWaCampaigns();
        };

        if (this.sendingInterval) clearInterval(this.sendingInterval);
        this.sendingInterval = setInterval(async () => {
            const data = await this.api(url, 'POST');
            if (!data || data.error) { clearInterval(this.sendingInterval); return; }

            const total = data.total || 1;
            const done = (data.sent || 0) + (data.failed || 0);
            const pct = Math.round((done / total) * 100);
            const offset = 339.292 - (339.292 * pct / 100);

            document.getElementById('sendingPct').textContent = pct + '%';
            document.getElementById('sendingSent').textContent = data.sent || 0;
            document.getElementById('sendingFailed').textContent = data.failed || 0;
            document.getElementById('sendingRemaining').textContent = data.remaining || 0;
            document.getElementById('sendingCircle').style.strokeDashoffset = offset;

            if (data.done) {
                clearInterval(this.sendingInterval);
                document.getElementById('sendingTitle').textContent = 'تم الإرسال!';
                this.toast('تم إرسال الحملة بنجاح!', 'success');
                if (type === 'email') this.loadCampaigns(); else this.loadWaCampaigns();
            }
        }, 3000);
    },

    async resumeSending(id) {
        this.openSendingProgress(id, 'email');
    },

    async resumeCampaign(id) {
        await this.api(`/api/email-campaigns/${id}/resume`, 'POST');
        this.openSendingProgress(id, 'email');
    },

    async deleteCampaign(id) {
        if (!confirm('حذف هذه الحملة؟')) return;
        await this.api(`/api/email-campaigns/${id}`, 'DELETE');
        this.loadCampaigns();
    },

    async loadTemplates() {
        const templates = await this.api('/api/email-campaigns/templates');
        const icons = { promotion: 'fa-percentage', newsletter: 'fa-newspaper', welcome: 'fa-hand-peace', blank: 'fa-file' };
        document.getElementById('templatesGrid').innerHTML = templates.map(t => `
            <div class="template-card" data-id="${t.id}">
                <i class="fas ${icons[t.thumbnail] || 'fa-file'}"></i>
                <div class="template-card-name">${this.esc(t.name)}</div>
            </div>`).join('');
        document.querySelectorAll('.template-card').forEach(card => {
            card.onclick = () => {
                const tpl = templates.find(t => t.id == card.dataset.id);
                if (tpl) document.getElementById('campContent').value = tpl.html;
                this.closeModal('templatesModal');
            };
        });
        this.openModal('templatesModal');
    },

    previewEmail() {
        const html = document.getElementById('campContent').value;
        const frame = document.getElementById('previewFrame');
        frame.srcdoc = html || '<p style="text-align:center;padding:40px;color:#999">لا يوجد محتوى</p>';
        this.openModal('previewModal');
    },

    // ================================================================
    //                    WHATSAPP
    // ================================================================

    setupWhatsappEvents() {
        // Tab switching
        document.querySelectorAll('.service-tab[data-wtab]').forEach(tab => {
            tab.onclick = () => {
                document.querySelectorAll('.service-tab[data-wtab]').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                document.querySelectorAll('#whatsappView .service-panel').forEach(p => p.classList.remove('active'));
                const panels = { 'wa-campaigns': 'waCampaignsPanel', 'wa-settings': 'waSettingsPanel' };
                const panel = document.getElementById(panels[tab.dataset.wtab]);
                if (panel) panel.classList.add('active');
            };
        });

        document.getElementById('newWaCampaignBtn').onclick = () => this.openWaCampaignModal();
        document.getElementById('saveWaCampaignBtn').onclick = () => this.saveWaCampaign(false);
        document.getElementById('sendWaCampaignBtn').onclick = () => this.saveWaCampaign(true);

        // WA API settings form
        const waApiForm = document.getElementById('waApiForm');
        if (waApiForm) {
            waApiForm.onsubmit = async (e) => {
                e.preventDefault();
                const data = {
                    provider: document.getElementById('waProvider').value,
                    instance_id: document.getElementById('waInstanceId').value,
                    api_token: document.getElementById('waApiToken').value,
                    custom_url: document.getElementById('waCustomUrl')?.value || '',
                };
                const res = await this.api('/api/whatsapp/api-settings', 'POST', data);
                if (res.success) this.toast('تم حفظ إعدادات API بنجاح', 'success');
                else this.toast(res.error || 'خطأ في الحفظ', 'error');
            };
        }

        // Show/hide custom URL
        const waProvider = document.getElementById('waProvider');
        if (waProvider) {
            waProvider.onchange = () => {
                const g = document.getElementById('waCustomUrlGroup');
                if (g) g.classList.toggle('hidden', waProvider.value !== 'custom');
            };
        }
    },

    // ----- WA API Settings -----
    async loadWaApiSettings() {
        try {
            const data = await this.api('/api/whatsapp/api-settings');
            if (data.configured) {
                const providerEl = document.getElementById('waProvider');
                const instanceEl = document.getElementById('waInstanceId');
                if (providerEl) providerEl.value = data.provider || 'ultramsg';
                if (instanceEl) instanceEl.value = data.instance_id || '';
            }
        } catch(e) {}
    },

    async loadWaSessions() {
        // For API-based approach, just load settings
        this.loadWaApiSettings();
    },

    // ----- WA Campaigns -----
    async loadWaCampaigns() {
        const campaigns = await this.api('/api/whatsapp/campaigns');
        const statusText = { draft: 'مسودة', sending: 'جاري الإرسال', sent: 'تم الإرسال', paused: 'متوقف', failed: 'فشل' };
        document.getElementById('waCampaignsList').innerHTML = campaigns.length ? campaigns.map(c => {
            const pct = c.total_recipients ? Math.round(((c.sent_count + c.failed_count) / c.total_recipients) * 100) : 0;
            return `
            <div class="campaign-card">
                <div class="campaign-card-header">
                    <div class="campaign-card-title"><i class="fab fa-whatsapp" style="color:#25d366;margin-left:6px"></i>${this.esc(c.name)}</div>
                    <span class="campaign-status status-${c.status}">${statusText[c.status]}</span>
                </div>
                <div class="campaign-card-meta">
                    <span><i class="fas fa-users"></i> ${c.contacts_count || c.total_recipients || 0} جهة</span>
                    <span><i class="fab fa-whatsapp"></i> ${this.esc(c.session?.phone_number || 'غير محدد')}</span>
                    <span><i class="fas fa-clock"></i> تأخير: ${c.delay_seconds} ثانية</span>
                    ${c.total_recipients ? `<span><i class="fas fa-paper-plane"></i> ${c.sent_count}/${c.total_recipients}</span>` : ''}
                </div>
                ${c.total_recipients ? `<div class="campaign-progress"><div class="progress-bar"><div class="progress-fill" style="width:${pct}%;background:#25d366"></div></div></div>` : ''}
                <div class="campaign-card-actions">
                    ${c.status === 'draft' ? `<button class="btn btn-sm" style="background:#25d366;color:#fff" onclick="App.sendWaCampaign(${c.id})"><i class="fab fa-whatsapp"></i> إرسال</button>` : ''}
                    ${c.status === 'sending' ? `<button class="btn btn-sm btn-outline" onclick="App.openSendingProgress(${c.id},'whatsapp')"><i class="fas fa-chart-line"></i> متابعة</button>` : ''}
                    <button class="btn btn-sm btn-outline btn-danger" onclick="App.deleteWaCampaign(${c.id})"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
        }).join('') : '<div class="empty-state"><i class="fab fa-whatsapp" style="color:#25d366"></i><p>لم تقم بإنشاء حملات واتساب بعد</p></div>';
    },

    async openWaCampaignModal() {
        document.getElementById('waName').value = '';
        document.getElementById('waMessage').value = '';
        document.getElementById('waPhones').value = '';
        document.getElementById('waDelay').value = '5';
        document.getElementById('waFileInput').value = '';

        // Check if API is configured
        const settings = await this.api('/api/whatsapp/api-settings');
        if (!settings.configured) {
            this.toast('يجب إعداد WhatsApp API أولاً من تبويب إعدادات API', 'error');
            return;
        }

        // Hide session selector since we use API
        document.getElementById('waSession').closest('.form-group').style.display = 'none';
        this.openModal('waCampaignModal');
    },

    async saveWaCampaign(sendNow = false) {
        const data = {
            name: document.getElementById('waName').value,
            message: document.getElementById('waMessage').value,
            session_id: document.getElementById('waSession').value,
            phones: document.getElementById('waPhones').value,
            delay_seconds: parseInt(document.getElementById('waDelay').value),
        };
        if (!data.name || !data.message) { this.toast('يرجى ملء اسم الحملة والرسالة', 'error'); return; }

        const res = await this.api('/api/whatsapp/campaigns', 'POST', data);
        if (res.error) { this.toast(res.error, 'error'); return; }

        // Import file if selected
        const file = document.getElementById('waFileInput').files[0];
        if (file) {
            const fd = new FormData();
            fd.append('file', file);
            await this.upload(`/api/whatsapp/campaigns/${res.id}/import`, fd);
        }

        this.closeModal('waCampaignModal');
        this.loadWaCampaigns();
        this.toast('تم حفظ الحملة', 'success');

        if (sendNow) {
            await this.sendWaCampaign(res.id);
        }
    },

    async sendWaCampaign(id) {
        const res = await this.api(`/api/whatsapp/campaigns/${id}/send`, 'POST');
        if (res.error) { this.toast(res.error, 'error'); return; }
        this.openSendingProgress(id, 'whatsapp');
    },

    async deleteWaCampaign(id) {
        if (!confirm('حذف هذه الحملة؟')) return;
        await this.api(`/api/whatsapp/campaigns/${id}`, 'DELETE');
        this.loadWaCampaigns();
    },
};

// Start the app
document.addEventListener('DOMContentLoaded', () => App.init());
