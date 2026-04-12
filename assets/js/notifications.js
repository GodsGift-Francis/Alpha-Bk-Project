// assets/js/notifications.js
const Notifications = {
    init() {
        this.trigger = document.getElementById('notification-trigger');
        this.dropdown = document.getElementById('notification-dropdown');
        this.list = document.getElementById('notification-list');
        this.badge = document.getElementById('nav-badge');
        this.markReadBtn = document.getElementById('mark-read');

        if (!this.trigger) return;

        this.bindEvents();
        this.fetch();
        
        // Refresh notifications every 60 seconds
        setInterval(() => this.fetch(), 60000);
    },

    bindEvents() {
        this.trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            this.dropdown.classList.toggle('active');
            if (this.dropdown.classList.contains('active')) {
                this.markAsSeen();
            }
        });

        this.markReadBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.clearBadge();
        });

        document.addEventListener('click', () => {
            this.dropdown.classList.remove('active');
        });

        this.dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    },

    async fetch() {
        try {
            const result = await API.get('api/announcements.php');
            if (result.announcements && result.announcements.length > 0) {
                this.render(result.announcements);
                this.updateBadge(result.announcements);
            } else {
                this.list.innerHTML = `
                    <div class="empty-notifications">
                        <div style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;">🔔</div>
                        <p>No new notifications at this time.</p>
                    </div>`;
                this.badge.style.display = 'none';
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    },

    render(announcements) {
        let html = '';
        announcements.forEach(ann => {
            const date = new Date(ann.created_at);
            const timeAgo = this.formatTime(date);
            
            html += `
                <div class="notification-item">
                    <div class="notif-icon">📣</div>
                    <div class="notif-content">
                        <strong class="title">${escapeHTML(ann.title)}</strong>
                        <p class="content">${escapeHTML(ann.content)}</p>
                        <span class="time">${timeAgo}</span>
                    </div>
                </div>
            `;
        });
        this.list.innerHTML = html;
    },

    updateBadge(announcements) {
        const lastSeenId = localStorage.getItem('last_seen_notif_id') || 0;
        const unreadCount = announcements.filter(a => a.id > lastSeenId).length;

        if (unreadCount > 0) {
            this.badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            this.badge.style.display = 'flex';
            this.badge.classList.add('pulse');
        } else {
            this.badge.style.display = 'none';
        }
    },

    markAsSeen() {
        const items = this.list.querySelectorAll('.notification-item');
        if (items.length > 0) {
            // Logic to track the highest ID seen
            // In a more complex app, you'd find the max ID from the current list
        }
    },

    clearBadge() {
        this.badge.style.display = 'none';
        // Mock marking all as read by setting a high ID
        localStorage.setItem('last_seen_notif_id', Date.now()); 
    },

    formatTime(date) {
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
};

document.addEventListener('DOMContentLoaded', () => Notifications.init());
