/**
 * A very small client-side router.
 *
 * The app is served by one Blade view and Laravel's fallback route hands every
 * browser path to it, so the client only has to read `location.pathname`, match
 * it against the route table, and push history entries when the user navigates.
 * No dependency, no hash fragments — paths stay shareable and bookmarkable.
 */
import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';

const RouterContext = createContext(null);

function currentPath() {
    const path = window.location.pathname.replace(/\/+$/, '');

    return path === '' ? '/' : path;
}

/** Push a new entry onto the history and re-render the route. */
export function navigate(to, { replace = false } = {}) {
    if (to === window.location.pathname) {
        return;
    }

    if (replace) {
        window.history.replaceState({}, '', to);
    } else {
        window.history.pushState({}, '', to);
    }

    window.dispatchEvent(new PopStateEvent('popstate'));
    window.scrollTo({ top: 0 });
}

export function RouterProvider({ children }) {
    const [path, setPath] = useState(currentPath);

    useEffect(() => {
        const onChange = () => setPath(currentPath());

        window.addEventListener('popstate', onChange);

        return () => window.removeEventListener('popstate', onChange);
    }, []);

    return <RouterContext.Provider value={useMemo(() => ({ path }), [path])}>{children}</RouterContext.Provider>;
}

export function useRouter() {
    const context = useContext(RouterContext);

    if (context === null) {
        throw new Error('useRouter() must be used inside <RouterProvider>.');
    }

    return context;
}

/**
 * Match a path pattern against a path.
 *
 * `:name` segments become parameters; a null return means "no match".
 *
 * @returns {Record<string, string> | null}
 */
export function matchPath(pattern, path) {
    const patternParts = pattern.split('/').filter(Boolean);
    const pathParts = path.split('/').filter(Boolean);

    if (patternParts.length !== pathParts.length) {
        return null;
    }

    /** @type {Record<string, string>} */
    const params = {};

    for (let index = 0; index < patternParts.length; index += 1) {
        const part = patternParts[index];

        if (part.startsWith(':')) {
            params[part.slice(1)] = decodeURIComponent(pathParts[index]);
        } else if (part !== pathParts[index]) {
            return null;
        }
    }

    return params;
}

/** The parameters of the first route pattern that matches the current path. */
export function useParams(pattern) {
    const { path } = useRouter();

    return matchPath(pattern, path) ?? {};
}

/** Internal link that keeps the SPA behaviour (no full page reload). */
export function Link({ to, children, className = '', onClick, ...rest }) {
    return (
        <a
            href={to}
            className={className}
            onClick={(event) => {
                onClick?.(event);

                if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
                    return;
                }

                event.preventDefault();
                navigate(to);
            }}
            {...rest}
        >
            {children}
        </a>
    );
}
