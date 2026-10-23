const Notifications = {
    init() {
        this.trigger = document.querySelector('.notification-bell-btn');
        if (!this.trigger) return;
        this.dropdown = document.getElementById('notification-dropdown');
        this.list = document.getElementById('notification-list');
        this.badge = document.getElementById('nav-badge');
        this.read = document.getElementById('mark-read');
        this.trigger.addEventListener('click', () => this.setOpen(this.trigger.getAttribute('aria-expanded') !== 'true'));
        document.addEventListener('click', event => { if (!event.target.closest('#notification-trigger')) this.setOpen(false); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && this.trigger.getAttribute('aria-expanded') === 'true') { this.setOpen(false); this.trigger.focus(); } });
        this.read.addEventListener('click', async () => {
            if (!this.latest) return;
            this.read.disabled = true;
            const result = await API.post('api/notification-read.php', { last_read_id: this.latest });
            if (result.error) { showMessage(this.list, result.error, true); this.read.disabled = false; }
            else await this.fetch();
        });
        this.fetch();
        setInterval(() => { if (!document.hidden) this.fetch(); }, 60000);
    },
    setOpen(open) {
        this.trigger.setAttribute('aria-expanded', String(open));
        this.dropdown.classList.toggle('active', open);
    },
    async fetch() {
        const result = await API.get('api/announcements.php');
        if (result.error) { showMessage(this.list, result.error, true); return; }
        this.latest = result.announcements[0]?.id;
        this.read.disabled = !result.unread_count;
        this.badge.hidden = !result.unread_count;
        this.badge.style.display = result.unread_count ? 'flex' : 'none';
        this.badge.textContent = result.unread_count > 99 ? '99+' : result.unread_count;
        this.trigger.setAttribute('aria-label', `Notifications, ${result.unread_count} unread`);
        this.list.innerHTML = result.announcements.length ? result.announcements.map(item => `<article class="notification-item ${item.id > result.last_read_id ? 'is-unread' : ''}"><div class="notif-content"><strong class="title">${escapeHTML(item.title)}</strong><p class="content">${escapeHTML(item.content)}</p><span class="time">${escapeHTML(displayDate(item.created_at))}</span></div></article>`).join('') : '<div class="empty-notifications">No announcements yet.</div>';
    }
};
document.addEventListener('DOMContentLoaded', () => Notifications.init());
