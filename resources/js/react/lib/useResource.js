/**
 * Data loading hook.
 *
 * Every page in this app loads one or two resources and then renders them; this
 * keeps that pattern in one place so each page is about its content, not about
 * loading state. `reload` exists because two flows need it: retrying after the
 * ML service was unreachable, and refreshing a list after creating a row.
 */
import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * @param {(options: {signal: AbortSignal}) => Promise<any>} loader
 * @param {Array<unknown>} deps
 */
export function useResource(loader, deps = [], { enabled = true } = {}) {
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(enabled);
    const [refreshKey, setRefreshKey] = useState(0);
    const loaderRef = useRef(loader);
    const controllerRef = useRef(null);

    loaderRef.current = loader;

    useEffect(() => {
        if (!enabled) {
            setLoading(false);

            return undefined;
        }

        controllerRef.current?.abort();
        const controller = new AbortController();
        controllerRef.current = controller;
        let cancelled = false;

        setLoading(true);

        loaderRef
            .current({ signal: controller.signal })
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                    setError(null);
                }
            })
            .catch((cause) => {
                // An aborted request is the component unmounting or reloading,
                // not a failure worth showing.
                if (!cancelled && cause?.name !== 'AbortError') {
                    setError(cause);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [...deps, refreshKey, enabled]);

    const reload = useCallback(() => setRefreshKey((key) => key + 1), []);

    return { data, error, loading, reload, setData };
}
