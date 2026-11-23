(() => {
    'use strict';

    const content = document.getElementById('atm-template-content');
    let pending;
    let previousMeasurement;

    function visibleChildren(element) {
        return [...element.children].filter(child => {
            return !['STYLE', 'SCRIPT', 'LINK'].includes(child.tagName) && child.getClientRects().length;
        });
    }

    function templateRoots() {
        return visibleChildren(content.querySelector(':scope > .elementor') || content);
    }

    function standaloneAtomicForm() {
        let roots = templateRoots();
        while (roots.length === 1) {
            const element = roots[0];
            if (element.matches('form[data-element_type="e-form"]')) return element;
            if (!element.matches('.e-con, .e-con-inner, .elementor-container')) break;
            roots = visibleChildren(element);
        }
        return null;
    }

    function send(type, data = {}) {
        window.parent.postMessage({ source: 'atm-menu', type, ...data }, location.origin);
    }

    function templateWidth() {
        const roots = templateRoots();
        const available = document.documentElement.clientWidth;
        const widths = roots.map(root => {
            const outerWidth = root.getBoundingClientRect().width;
            if (Math.abs(outerWidth - available) > 2) return outerWidth;

            // Classic boxed sections and Atomic wrappers can put the width
            // on their only inner container instead of the fluid outer one.
            let box = root.querySelector(':scope > .e-con-inner, :scope > .elementor-container');
            if (!box) {
                const children = visibleChildren(root);
                if (children.length === 1 && children[0].matches('.e-con, .e-atomic-element, form')) {
                    box = children[0];
                }
            }
            const innerWidth = box?.getBoundingClientRect().width || 0;
            const style = getComputedStyle(root);
            const insets = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight) +
                parseFloat(style.borderLeftWidth) + parseFloat(style.borderRightWidth);
            return innerWidth && Math.abs(innerWidth + insets - available) > 2 ? innerWidth + insets : 0;
        });
        return widths.length && widths.every(Boolean) ? Math.ceil(Math.max(...widths)) : null;
    }

    function measure() {
        pending = undefined;
        if (content.classList.contains('atm-expanded-layout')) return;
        const form = standaloneAtomicForm();
        content.classList.toggle('atm-form-layout', Boolean(form));
        const bounds = form?.getBoundingClientRect();
        const size = {
            width: bounds?.width ? Math.ceil(bounds.width) : templateWidth(),
            height: Math.ceil(Math.max(content.getBoundingClientRect().height, content.scrollHeight)),
        };
        if (bounds?.width) {
            size.formLayout = {
                viewportWidth: window.innerWidth,
                left: bounds.left,
                radius: getComputedStyle(form).borderRadius
            };
        }
        const measurement = JSON.stringify(size);
        if (measurement === previousMeasurement) return;
        previousMeasurement = measurement;
        send('size', size);
    }

    function scheduleMeasure() {
        if (pending === undefined) pending = requestAnimationFrame(measure);
    }

    function syncExpandedLayout() {
        const frame = window.frameElement;
        const dialog = frame?.closest('#atm-dialog');
        if (!dialog) return;

        function sync() {
            const expanded = dialog.open && dialog.hasAttribute('data-expanded') &&
                frame.hasAttribute('data-active') && frame.hasAttribute('data-atm-ready');
            if (expanded === content.classList.contains('atm-expanded-layout')) return;

            content.classList.toggle('atm-expanded-layout', expanded);
            previousMeasurement = undefined;
            scheduleMeasure();
        }

        const observer = new MutationObserver(sync);
        observer.observe(dialog, { attributes: true, attributeFilter: ['open', 'data-expanded'] });
        observer.observe(frame, { attributes: true, attributeFilter: ['data-active', 'data-atm-ready'] });
        window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
        sync();
    }

    function inheritSiteTheme() {
        if (window.parent === window) return;
        let parentRoot;
        try {
            parentRoot = window.parent.document.documentElement;
        } catch {
            return;
        }
        const root = document.documentElement;
        const options = { attributes: true, attributeFilter: ['data-lnktamode', 'data-lnktatheme'] };
        function sync() {
            const mode = parentRoot.getAttribute('data-lnktamode');
            const theme = parentRoot.getAttribute('data-lnktatheme');
            if (theme !== 'light' && theme !== 'dark') return;

            const darkMode = window.LNKTADarkMode;
            if (['light', 'dark', 'system'].includes(mode) && darkMode && darkMode.getMode() !== mode) {
                darkMode.setMode(mode);
            }
            if (mode && root.getAttribute('data-lnktamode') !== mode) root.setAttribute('data-lnktamode', mode);
            if (root.getAttribute('data-lnktatheme') !== theme) root.setAttribute('data-lnktatheme', theme);
            if (root.style.colorScheme !== theme) root.style.colorScheme = theme;
        }
        const observer = new MutationObserver(sync);
        observer.observe(parentRoot, options);
        observer.observe(root, options);
        window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
        sync();
    }

    function addMissingFormMessages() {
        const messages = document.getElementById('atm-form-messages');
        for (const form of content.querySelectorAll('form[data-element_type="e-form"]')) {
            for (const fallback of messages.content.children) {
                const type = fallback.getAttribute('data-e-type');
                if (form.querySelector(`[data-e-type="${type}"], [data-element_type="${type}"]`)) continue;
                form.append(fallback.cloneNode(true));
            }
        }
    }

    function updateMediaHeadings() {
        const headingSelector = 'h1, h2, h3, h4, h5, h6, [role="heading"]';
        for (const media of content.querySelectorAll('[data-atm-label-target]')) {
            const target = media.dataset.atmLabelTarget;
            const element = target.startsWith('.')
                ? content.getElementsByClassName(target.slice(1))[0]
                : document.getElementById(target.replace(/^#/, ''));
            if (!element || !content.contains(element)) continue;

            const heading = element.matches(headingSelector) ? element : element.querySelector(headingSelector);
            if (heading) heading.textContent = media.dataset.atmLabel;
        }
    }

    function syncYouTubePlayers() {
        const players = content.querySelectorAll('iframe[data-atm-youtube]');
        if (!players.length) return;

        const frame = window.frameElement;
        const dialog = frame?.closest('#atm-dialog');
        function sync() {
            const visible = window.parent === window || (dialog?.open && frame.hasAttribute('data-active'));
            for (const player of players) {
                if (visible && !player.hasAttribute('src')) {
                    player.setAttribute('src', player.dataset.atmYoutube);
                } else if (!visible && player.hasAttribute('src')) {
                    player.removeAttribute('src');
                }
            }
        }

        if (dialog) {
            const observer = new MutationObserver(sync);
            observer.observe(dialog, { attributes: true, attributeFilter: ['open'] });
            observer.observe(frame, { attributes: true, attributeFilter: ['data-active'] });
            window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
        }
        sync();
    }

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        send('close');
    });
    document.addEventListener('click', event => {
        const link = event.target.closest('a');
        const hash = link?.getAttribute('href');
        if (!hash?.startsWith('#') || hash.length === 1) return;
        event.preventDefault();
        if (hash === '#async-menu-close') send('close');
        else send('anchor', { hash });
    });

    inheritSiteTheme();
    addMissingFormMessages();
    updateMediaHeadings();
    syncExpandedLayout();
    syncYouTubePlayers();
    new ResizeObserver(scheduleMeasure).observe(content);
    window.addEventListener('load', scheduleMeasure, { once: true });
    window.addEventListener('resize', () => {
        previousMeasurement = undefined;
        scheduleMeasure();
    });
    document.fonts?.ready.then(scheduleMeasure);
    scheduleMeasure();
})();
