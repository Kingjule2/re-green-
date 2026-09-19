/**
 * The signed-in account, for the whole app.
 *
 * Session authentication means the browser holds no token; the only state the
 * app has to keep is who the server says it is. Every route outside the public
 * pages reads the account from here, and the shell uses `viewsAllLands` to
 * decide which navigation a pemda/NGO or corporate account gets.
 */
import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { auth as authApi } from '@/react/lib/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;

        authApi
            .me()
            .then((me) => {
                if (!cancelled) setUser(me);
            })
            .catch(() => {
                // No session (or an expired one) is a normal state: the public
                // pages render, and protected routes send the user to /login.
                if (!cancelled) setUser(null);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    const login = useCallback(async (credentials) => {
        const me = await authApi.login(credentials);
        setUser(me);

        return me;
    }, []);

    const register = useCallback(async (account) => {
        const me = await authApi.register(account);
        setUser(me);

        return me;
    }, []);

    const logout = useCallback(async () => {
        await authApi.logout();
        setUser(null);
    }, []);

    const value = useMemo(
        () => ({
            user,
            loading,
            login,
            register,
            logout,
            isFarmer: user?.role === 'farmer',
            viewsAllLands: user?.views_all_lands ?? false,
            canManageLands: user?.role === 'farmer',
        }),
        [user, loading, login, register, logout],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const context = useContext(AuthContext);

    if (context === null) {
        throw new Error('useAuth() must be used inside <AuthProvider>.');
    }

    return context;
}
