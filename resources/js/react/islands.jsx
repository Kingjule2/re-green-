/**
 * React "islands" mounting.
 *
 * This app is Blade-first: Laravel serves one view and React takes over the
 * `#root` element. Additional islands can be dropped into any Blade view with
 *
 *   <div data-react="SomeComponent" data-props='{"heading":"re:green"}'></div>
 *
 * `data-react` — the key in the `components` registry below.
 * `data-props` — optional JSON object passed to the component as props.
 */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import RegreenApp from '@/react/RegreenApp';

/** @type {Record<string, import('react').ComponentType<any>>} */
const components = {
    RegreenApp,
};

function parseProps(el) {
    const raw = el.dataset.props;
    if (!raw) return {};
    try {
        return JSON.parse(raw);
    } catch (error) {
        console.error('[islands] Invalid data-props JSON on', el, error);
        return {};
    }
}

export function mountIslands(root = document) {
    const rootEl = root.getElementById ? root.getElementById('root') : root.querySelector?.('#root');
    if (rootEl && !rootEl.dataset.reactMounted) {
        rootEl.dataset.reactMounted = 'true';
        createRoot(rootEl).render(
            <StrictMode>
                <RegreenApp />
            </StrictMode>,
        );
    }

    const nodes = root.querySelectorAll('[data-react]:not([data-react-mounted])');

    nodes.forEach((el) => {
        const name = el.dataset.react;
        const Component = components[name];

        if (!Component) {
            console.warn(`[islands] No React component registered for "${name}".`);
            return;
        }

        el.dataset.reactMounted = 'true';
        createRoot(el).render(
            <StrictMode>
                <Component {...parseProps(el)} />
            </StrictMode>,
        );
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => mountIslands());
} else {
    mountIslands();
}
