(() => {
    'use strict';

    const config = window.ATMMenu;
    if (!config) return;

    const frames = new Map();
    const triggerSelector = 'a[href^="#async-menu-"]';
    let dialog;
    let status;
    let expandButton;
    let active;
    let trigger;
    let previousOverflow;

    function templateRequest(href) {
        const match = /^#async-menu-(\d+)(?:-([a-zA-Z0-9_][a-zA-Z0-9_-]{0,127}))?$/.exec(href);
        if (!match) return null;
        const id = Number(match[1]);
        if (!config.ids.includes(id)) return null;
        const field = match[2] || '';
        return { id, field, key: `${id}:${field}` };
    }

    function mark(open) {
        document.querySelectorAll(triggerSelector).forEach(link => {
            const request = templateRequest(link.getAttribute('href'));
            if (!request) return;
            link.setAttribute('aria-haspopup', 'dialog');
            link.setAttribute('aria-controls', 'atm-dialog');
            link.setAttribute('aria-expanded', String(open && active?.key === request.key));
        });
    }

    function createDialog() {
        if (dialog) return;
        dialog = document.createElement('dialog');
        dialog.id = 'atm-dialog';
        dialog.setAttribute('aria-label', 'Template panel');
        dialog.innerHTML = `
            <div class="atm-toolbar">
                <button type="button" class="atm-expand" aria-label="Full screen" aria-pressed="false" title="Expand panel" hidden>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path class="atm-expand-icon" d="M9 4H4v5m11-5h5v5M4 15v5h5m6 0h5v-5"/>
                        <path class="atm-restore-icon" d="M4 9h5V4m11 5h-5V4M4 15h5v5m11-5h-5v5"/>
                    </svg>
                </button>
                <button type="button" class="atm-close" aria-label="Close panel" autofocus>×</button>
            </div>
            <div class="atm-status" role="status">Loading…</div>`;
        expandButton = dialog.querySelector('.atm-expand');
        expandButton.onclick = () => setExpanded(!dialog.hasAttribute('data-expanded'));
        dialog.querySelector('.atm-close').onclick = () => dialog.close();
        status = dialog.querySelector('.atm-status');
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right ||
                event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
        });
        dialog.addEventListener('close', () => {
            document.documentElement.style.overflow = previousOverflow;
            setExpanded(false);
            mark(false);
            trigger?.focus();
        });
        document.body.append(dialog);
    }

    function setExpanded(expanded) {
        dialog.toggleAttribute('data-expanded', expanded);
        expandButton.setAttribute('aria-pressed', String(expanded));
        expandButton.title = expanded ? 'Restore panel size' : 'Expand panel';
    }

    function updatePanel() {
        dialog.style.setProperty('--atm-panel-width', `${active.width || config.width}px`);
        dialog.style.setProperty('--atm-panel-height', `${active.ready ? Math.max(1, active.height) : 88}px`);
        dialog.style.borderRadius = active.ready ? active.formRadius ?? '' : '';
        status.hidden = active.ready;
        expandButton.hidden = !active.ready && !dialog.hasAttribute('data-expanded');
        if (active.ready) return;

        status.textContent = active.failed ? 'Template could not load. ' : 'Loading…';
        if (!active.failed) return;
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.textContent = 'Retry';
        retry.onclick = () => load(active);
        status.append(retry);
    }

    function fail(entry) {
        if (entry.ready) return;
        clearTimeout(entry.timer);
        entry.failed = true;
        if (entry === active) updatePanel();
    }

    function load(entry) {
        clearTimeout(entry.timer);
        clearTimeout(entry.settle);
        entry.ready = entry.failed = false;
        entry.width = undefined;
        entry.formRadius = undefined;
        entry.frame.removeAttribute('data-atm-form-layout');
        entry.frame.style.removeProperty('--atm-frame-width');
        entry.frame.style.removeProperty('--atm-frame-offset');
        entry.timer = setTimeout(() => fail(entry), 20000);
        const url = new URL(location.href);
        url.hash = '';
        url.searchParams.set('atm_frame', '1');
        url.searchParams.set('atm_id', entry.id);
        url.searchParams.delete('atm_field');
        if (entry.field) url.searchParams.set('atm_field', entry.field);
        entry.frame.src = url.href;
        if (entry === active) updatePanel();
    }

    function getFrame(request) {
        if (frames.has(request.key)) return frames.get(request.key);
        createDialog();
        const frame = document.createElement('iframe');
        frame.title = 'Template content';
        const entry = { ...request, frame };
        frames.set(request.key, entry);
        frame.addEventListener('error', () => fail(entry));
        dialog.append(frame);
        load(entry);
        return entry;
    }

    function showSize(entry, height) {
        clearTimeout(entry.settle);
        clearTimeout(entry.timer);
        entry.height = height;
        entry.ready = true;
        if (entry === active) updatePanel();
    }

    window.addEventListener('message', event => {
        if (event.origin !== location.origin || event.data?.source !== 'atm-menu') return;
        const entry = [...frames.values()].find(value => value.frame.contentWindow === event.source);
        if (!entry) return;
        const { type, width, height, hash, formLayout } = event.data;

        if (type === 'size' && Number.isFinite(height) && height >= 0) {
            if (formLayout && Number.isFinite(formLayout.left) && Number.isFinite(width) && width > 0) {
                entry.frame.setAttribute('data-atm-form-layout', '');
                // Measure the form again once its iframe has the page's breakpoints.
                if (entry.frame.clientWidth !== formLayout.viewportWidth) return;
                entry.width = width;
                entry.formRadius = formLayout.radius;
                entry.frame.style.setProperty('--atm-frame-offset', `${-formLayout.left}px`);
                showSize(entry, height);
                return;
            }
            if (entry.frame.hasAttribute('data-atm-form-layout')) {
                entry.frame.removeAttribute('data-atm-form-layout');
                entry.frame.style.removeProperty('--atm-frame-offset');
                entry.formRadius = undefined;
                entry.width = undefined;
            }
            if (entry.width === undefined) {
                const preferredWidth = Number.isFinite(width) && width > 0 ? width : config.width;
                entry.width = preferredWidth;
                const previousWidth = entry.frame.clientWidth;
                entry.frame.style.setProperty('--atm-frame-width', `${preferredWidth}px`);
                if (entry.frame.clientWidth !== previousWidth) {
                    // Wait for Elementor to reflow at its final width.
                    entry.settle = setTimeout(() => showSize(entry, height), 400);
                    return;
                }
            }
            showSize(entry, height);
            return;
        }

        if (entry !== active || !dialog.open) return;
        if (type === 'close') dialog.close();
        if (type === 'anchor' && typeof hash === 'string' && /^#[^\s]+$/.test(hash)) {
            const request = templateRequest(hash);
            if (request) {
                showTemplate(request);
                return;
            }
            dialog.close();
            location.hash = hash;
        }
    });

    window.addEventListener('resize', () => {
        frames.forEach(entry => {
            if (entry.frame.hasAttribute('data-atm-form-layout')) return;
            entry.width = undefined;
            entry.frame.style.removeProperty('--atm-frame-width');
        });
    });

    function showTemplate(request) {
        const entry = getFrame(request);
        active?.frame.removeAttribute('data-active');
        active = entry;
        active.frame.setAttribute('data-active', '');
        updatePanel();

        if (!dialog.open) {
            previousOverflow = document.documentElement.style.overflow;
            dialog.showModal();
            document.documentElement.style.overflow = 'hidden';
        }
        mark(true);
    }

    document.addEventListener('click', event => {
        const link = event.target.closest(triggerSelector);
        if (!link) return;
        const request = templateRequest(link.getAttribute('href'));
        if (!request) return;
        event.preventDefault();
        trigger = link;
        if (dialog?.open && active.key === request.key) dialog.close();
        else showTemplate(request);
    });
    mark(false);

    if (config.preload) {
        const schedule = () => {
            if (navigator.connection?.saveData) return;
            const preload = () => document.querySelectorAll(triggerSelector).forEach(link => {
                const request = templateRequest(link.getAttribute('href'));
                if (request) getFrame(request);
            });
            if ('requestIdleCallback' in window) requestIdleCallback(preload, { timeout: 5000 });
            else setTimeout(preload, 1500);
        };
        if (document.readyState === 'complete') schedule();
        else window.addEventListener('load', schedule, { once: true });
    }
})();
