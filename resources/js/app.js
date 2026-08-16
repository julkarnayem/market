import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

window.Alpine = Alpine;
Alpine.plugin(collapse);

// Global modal store
Alpine.store('modals', { active: null });

// Toast store
Alpine.store('toasts', {
    items: [],
    add(msg, type = 'success', duration = 4000) {
        const id = Date.now();
        this.items.push({ id, msg, type });
        setTimeout(() => this.remove(id), duration);
    },
    remove(id) { this.items = this.items.filter(t => t.id !== id); },
});

Alpine.start();
