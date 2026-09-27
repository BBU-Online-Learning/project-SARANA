(() => {
    let navigationController = null;
    let chatContentCache = null;

    function revealLearningSection() {
        let sectionId;
        try {
            sectionId = decodeURIComponent((window.location?.hash || '').slice(1));
        } catch {
            return;
        }
        if (!sectionId) return;

        const section = document.getElementById(sectionId);
        if (!section) return;

        let disclosure = section.closest('details');
        while (disclosure) {
            disclosure.open = true;
            disclosure = disclosure.parentElement?.closest('details');
        }
        section.scrollIntoView({ block: 'start' });
        section.querySelector?.('input:not([type="hidden"]), select, textarea')?.focus({ preventScroll: true });
    }

    function initializeContent(root = document) {
        root.querySelector?.('[data-validation-summary]')?.focus();
        root.querySelectorAll?.('[data-pending-form]').forEach((form) => {
            if (form.dataset.pendingReady === 'true') return;
            form.dataset.pendingReady = 'true';
            form.addEventListener('submit', (event) => {
                if (form.dataset.submitting === 'true') {
                    event.preventDefault();
                    return;
                }
                form.dataset.submitting = 'true';
                form.setAttribute('aria-busy', 'true');
                const button = form.querySelector('[data-pending-label]');
                if (button) {
                    button.disabled = true;
                    button.textContent = button.dataset.pendingLabel;
                }
                const status = form.querySelector('[data-form-status]');
                if (status) status.textContent = 'Saving your changes. Please wait.';
            });
        });
        root.querySelector?.('[data-chat-list]')?.addEventListener('click', () => {
            document.body.classList.remove('chat-mobile-room');
        });
        revealLearningSection();
        window.SettingsPage?.refresh?.();
    }

    function isModifiedClick(event) {
        return event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
    }

    function canNavigate(link, event) {
        if (!link?.matches?.('[data-workspace-nav]') || isModifiedClick(event)) return false;
        if (link.target && link.target !== '_self') return false;
        if (link.hasAttribute('download')) return false;

        const destination = new URL(link.href, window.location.href);
        return destination.origin === window.location.origin;
    }

    function setNavigationState(loading, label = '') {
        const main = document.getElementById('main-content');
        const status = document.getElementById('workspace-navigation-status');
        document.body.classList.toggle('workspace-navigating', loading);
        main?.setAttribute('aria-busy', String(loading));
        if (status) status.textContent = label;
    }

    function hasPageAsset(attribute, key) {
        return [...document.querySelectorAll(`[${attribute}]`)]
            .some((asset) => asset.getAttribute(attribute) === key);
    }

    async function loadPageStyles(nextDocument) {
        const styles = [...nextDocument.querySelectorAll('link[data-workspace-page-style]')];
        await Promise.all(styles.map((source) => {
            const key = source.dataset.workspacePageStyle;
            if (!key || hasPageAsset('data-workspace-page-style', key)) return Promise.resolve();

            return new Promise((resolve, reject) => {
                const link = source.cloneNode(true);
                link.addEventListener('load', resolve, { once: true });
                link.addEventListener('error', () => reject(new Error(`Unable to load ${link.href}.`)), { once: true });
                const mobileStyle = document.querySelector('link[data-workspace-mobile-style]');
                if (mobileStyle) mobileStyle.before(link);
                else document.head.append(link);
            });
        }));
    }

    async function loadPageScripts(nextDocument) {
        const scripts = [...nextDocument.querySelectorAll('script[data-workspace-page-script]')];
        for (const source of scripts) {
            const key = source.dataset.workspacePageScript;
            if (!key || hasPageAsset('data-workspace-page-script', key)) continue;

            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                [...source.attributes].forEach((attribute) => script.setAttribute(attribute.name, attribute.value));
                if (source.src) {
                    script.addEventListener('load', resolve, { once: true });
                    script.addEventListener('error', () => reject(new Error(`Unable to load ${source.src}.`)), { once: true });
                } else {
                    script.textContent = source.textContent;
                }
                document.body.append(script);
                if (!source.src) resolve();
            });
        }
    }

    function cacheChatContent(main) {
        chatContentCache ||= document.createElement('div');
        chatContentCache.replaceChildren(...main.childNodes);
    }

    function restoreChatContent(main, nextMain) {
        if (!chatContentCache?.hasChildNodes()) return false;
        main.replaceChildren(...chatContentCache.childNodes);

        const cachedSidebar = main.querySelector('#chat-conversations');
        const freshSidebar = nextMain.querySelector('#chat-conversations');
        if (cachedSidebar && freshSidebar) {
            const search = cachedSidebar.querySelector('#chat-search-input')?.value || '';
            const filter = cachedSidebar.querySelector('[data-conversation-filter][aria-pressed="true"]')?.dataset.conversationFilter;
            cachedSidebar.replaceWith(freshSidebar);
            const searchInput = freshSidebar.querySelector('#chat-search-input');
            if (searchInput) searchInput.value = search;
            if (filter) {
                freshSidebar.querySelectorAll('[data-conversation-filter]').forEach(button => {
                    button.setAttribute('aria-pressed', String(button.dataset.conversationFilter === filter));
                });
            }
            const activeRoom = freshSidebar.querySelector(`.room-item[data-room-id="${window.chat?.activeRoomId}"]`);
            activeRoom?.classList.add('active');
            activeRoom?.setAttribute('aria-current', 'true');
            window.filterConversationList?.(search.trim().toLowerCase());
        }

        return true;
    }

    async function applyDocument(nextDocument, destination, updateHistory) {
        const nextMain = nextDocument.getElementById('main-content');
        const nextNavigation = nextDocument.querySelector('#workspace-navigation .workspace-nav');
        const nextHeaderContext = nextDocument.querySelector('.workspace-header-context');
        const nextMobileNavigation = nextDocument.querySelector('.workspace-mobile-nav');
        const currentMain = document.getElementById('main-content');
        const currentNavigation = document.querySelector('#workspace-navigation .workspace-nav');
        const currentHeaderContext = document.querySelector('.workspace-header-context');
        const currentMobileNavigation = document.querySelector('.workspace-mobile-nav');

        if (!nextMain || !nextNavigation || !nextHeaderContext || !nextMobileNavigation || !currentMain || !currentNavigation || !currentHeaderContext || !currentMobileNavigation) {
            return false;
        }

        const leavingChat = document.body.classList.contains('workspace-chat')
            && !nextDocument.body.classList.contains('workspace-chat');
        const enteringChat = nextDocument.body.classList.contains('workspace-chat');

        if (leavingChat) cacheChatContent(currentMain);

        const sidebarCollapsed = document.body.classList.contains('workspace-sidebar-collapsed');
        document.title = nextDocument.title;
        document.body.className = nextDocument.body.className;
        document.body.classList.toggle('workspace-sidebar-collapsed', sidebarCollapsed);
        const restoredChat = enteringChat && restoreChatContent(currentMain, nextMain);
        if (!restoredChat) currentMain.innerHTML = nextMain.innerHTML;
        currentNavigation.replaceWith(nextNavigation);
        currentHeaderContext.replaceWith(nextHeaderContext);
        currentMobileNavigation.replaceWith(nextMobileNavigation);

        await loadPageStyles(nextDocument);
        await loadPageScripts(nextDocument);
        if (restoredChat && window.chat?.activeRoomId) await window.loadRoom?.(window.chat.activeRoomId);

        if (updateHistory) window.history.pushState({ workspaceNavigation: true }, '', destination);

        document.body.classList.remove('workspace-nav-open');
        const backdrop = document.querySelector('.workspace-backdrop');
        if (backdrop) backdrop.hidden = true;
        document.querySelector('[data-shell-toggle]')?.setAttribute('aria-expanded', 'false');

        initializeContent(currentMain);
        if (destination.hash) {
            revealLearningSection();
        } else {
            window.scrollTo({ top: 0, behavior: 'auto' });
            currentMain.focus({ preventScroll: true });
        }

        return true;
    }

    async function navigate(destination, { updateHistory = true } = {}) {
        const url = destination instanceof URL ? destination : new URL(destination, window.location.href);
        const current = new URL(window.location.href);

        if (url.pathname === current.pathname && url.search === current.search) {
            if (updateHistory) window.history.pushState({ workspaceNavigation: true }, '', url);
            revealLearningSection();
            return;
        }

        navigationController?.abort();
        navigationController = new AbortController();
        setNavigationState(true, `Loading ${url.pathname.split('/').filter(Boolean).pop() || 'dashboard'}…`);

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-cache',
                signal: navigationController.signal,
                headers: {
                    Accept: 'text/html',
                    'X-Workspace-Navigation': 'true',
                },
            });
            if (!response.ok) throw new Error(`Workspace navigation failed with ${response.status}.`);

            const finalUrl = new URL(response.url || url, window.location.href);
            finalUrl.hash = url.hash;
            const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (!await applyDocument(nextDocument, finalUrl, updateHistory)) {
                window.location.assign(finalUrl);
                return;
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            window.location.assign(url);
        } finally {
            setNavigationState(false, 'Page loaded.');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.querySelector('[data-shell-toggle]');
        const collapse = document.querySelector('[data-shell-collapse]');
        const backdrop = document.querySelector('.workspace-backdrop');
        const menu = document.getElementById('workspace-navigation');
        const desktop = window.matchMedia('(min-width: 992px)');
        const storageKey = 'workspace-sidebar-collapsed';
        const storedSidebarState = () => {
            try {
                return window.localStorage.getItem(storageKey) === 'true';
            } catch {
                return false;
            }
        };
        const saveSidebarState = (collapsed) => {
            try {
                window.localStorage.setItem(storageKey, String(collapsed));
            } catch {
                // The sidebar still works when browser storage is unavailable.
            }
        };
        const setCollapsed = (collapsed, persist = true) => {
            const applied = desktop.matches && collapsed;
            document.body.classList.toggle('workspace-sidebar-collapsed', applied);
            collapse?.setAttribute('aria-expanded', String(!applied));
            const label = applied ? 'Expand navigation' : 'Close navigation';
            collapse?.setAttribute('aria-label', label);
            collapse?.setAttribute('title', label);
            const collapseLabel = collapse?.querySelector('[data-shell-collapse-label]');
            if (collapseLabel) collapseLabel.textContent = label;
            const icon = collapse?.querySelector('i');
            icon?.classList.toggle('ti-layout-sidebar-left-collapse', !applied);
            icon?.classList.toggle('ti-layout-sidebar-left-expand', applied);
            if (persist && desktop.matches) saveSidebarState(applied);
        };
        const setMenu = (open) => {
            document.body.classList.toggle('workspace-nav-open', open);
            toggle?.setAttribute('aria-expanded', String(open));
            if (backdrop) backdrop.hidden = !open;
            if (open) requestAnimationFrame(() => menu?.querySelector('button, a')?.focus());
            else toggle?.focus();
        };

        setCollapsed(storedSidebarState(), false);
        toggle?.addEventListener('click', () => setMenu(!document.body.classList.contains('workspace-nav-open')));
        collapse?.addEventListener('click', () => {
            if (desktop.matches) {
                setCollapsed(!document.body.classList.contains('workspace-sidebar-collapsed'));
                return;
            }
            setMenu(false);
        });
        desktop.addEventListener?.('change', () => setCollapsed(storedSidebarState(), false));
        document.querySelectorAll('[data-shell-close]').forEach((button) => button.addEventListener('click', () => setMenu(false)));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && document.body.classList.contains('workspace-nav-open')) setMenu(false);
            if (event.key === 'Tab' && document.body.classList.contains('workspace-nav-open')) {
                const links = [...menu.querySelectorAll('a, button')];
                const first = links[0];
                const last = links[links.length - 1];
                if (!menu.contains(document.activeElement)) {
                    event.preventDefault();
                    first.focus();
                } else if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
        document.addEventListener('click', (event) => {
            const link = event.target.closest?.('a[data-workspace-nav]');
            if (!canNavigate(link, event)) return;
            event.preventDefault();
            navigate(new URL(link.href, window.location.href));
        });
        window.addEventListener('popstate', () => navigate(window.location.href, { updateHistory: false }));
        window.addEventListener('hashchange', revealLearningSection);
        initializeContent();
    });

    window.WorkspaceNavigation = { canNavigate, navigate };
})();

window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
});
