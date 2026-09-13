document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-shell-toggle]');
    const backdrop = document.querySelector('.workspace-backdrop');
    const menu = document.getElementById('workspace-navigation');
    const setMenu = open => {
        document.body.classList.toggle('workspace-nav-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
        if (backdrop) backdrop.hidden = !open;
        if (open) requestAnimationFrame(() => menu?.querySelector('button, a')?.focus());
        else toggle?.focus();
    };
    toggle?.addEventListener('click', () => setMenu(!document.body.classList.contains('workspace-nav-open')));
    document.querySelectorAll('[data-shell-close]').forEach(button => button.addEventListener('click', () => setMenu(false)));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && document.body.classList.contains('workspace-nav-open')) setMenu(false);
        if (event.key === 'Tab' && document.body.classList.contains('workspace-nav-open')) {
            const links = [...menu.querySelectorAll('a, button')];
            const first = links[0], last = links[links.length - 1];
            if (!menu.contains(document.activeElement)) { event.preventDefault(); first.focus(); }
            else if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    document.querySelector('[data-validation-summary]')?.focus();
    document.querySelectorAll('[data-pending-form]').forEach(form => form.addEventListener('submit', () => {
        form.setAttribute('aria-busy', 'true');
        const button = form.querySelector('[data-pending-label]');
        button.disabled = true;
        button.textContent = button.dataset.pendingLabel;
        form.querySelector('[data-form-status]').textContent = 'Saving your changes. Please wait.';
    }));
    const revealLearningSection = () => {
        if (!document.body.classList.contains('learning-workspace')) return;
        let sectionId;
        try { sectionId = decodeURIComponent(window.location.hash.slice(1)); }
        catch { return; }
        if (!sectionId) return;
        const section = document.getElementById(sectionId);
        if (!section) return;
        let disclosure = section.closest('details');
        while (disclosure) {
            disclosure.open = true;
            disclosure = disclosure.parentElement?.closest('details');
        }
        section.scrollIntoView({ block: 'start' });
    };
    revealLearningSection();
    window.addEventListener('hashchange', revealLearningSection);
    document.querySelector('[data-chat-list]')?.addEventListener('click', () => {
        document.body.classList.remove('chat-mobile-room');
    });
});
window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
