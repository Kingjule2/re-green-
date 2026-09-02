/**
 * React "islands" mounting.
 *
 * This app is Blade-first. Rather than take over the whole page as an SPA, we
 * mount individual React components into placeholders that Blade renders. Drop
 * this into any Blade view:
 *
 *   <div data-react="GsapDemo" data-props='{"heading":"Regreen"}'></div>
 *
 * `data-react`  — the key in the `components` registry below.
 * `data-props`  — optional JSON object passed to the component as props.
 *
 * Add a component by importing it and adding one entry to `components`.
 */
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import GsapDemo from '@/react/components/GsapDemo';
import RegreenApp from '@/react/RegreenApp';

/** @type {Record<string, import('react').ComponentType<any>>} */
const components = {
    GsapDemo,
    RegreenApp,
};

function parseProps(el) {
    const raw = el.dataset.props;
    if (!raw) return {};
    try {
        return JSON.parse(raw);
    } catch (error) {
        console.error(`[islands] Invalid data-props JSON on`, el, error);
        return {};
    }
}

export function mountIslands(root = document) {
    // 1. Check for standard #root element
    const rootEl = root.getElementById ? root.getElementById('root') : root.querySelector?.('#root');
    if (rootEl && !rootEl.dataset.reactMounted) {
        rootEl.dataset.reactMounted = 'true';
        createRoot(rootEl).render(
            <StrictMode>
                <RegreenApp />
            </StrictMode>,
        );
    }

    // 2. Check for data-react islands
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
