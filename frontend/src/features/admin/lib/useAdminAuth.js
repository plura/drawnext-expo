// src/features/admin/lib/useAdminAuth.js
/**
 * useAdminAuth()
 * ---------------------------------------
 * Small auth hook for the admin area.
 *
 * Responsibilities:
 * - Load current admin session (GET /api/admin/me)
 * - Provide login(email) / logout() actions
 * - Expose { loading, isAdmin, email, error }
 *
 * Typical usage:
 *   const { loading, isAdmin, email, login, logout, refresh } = useAdminAuth()
 *   if (loading) return <Spinner/>
 *   if (!isAdmin) return <AdminLoginForm onSubmit={login}/>
 */

import { useCallback, useEffect, useMemo, useState } from "react";
import { getSession, login as apiLogin, logout as apiLogout } from "./api";

export function useAdminAuth() {
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [email, setEmail] = useState(null);
	const [isAdmin, setIsAdmin] = useState(false);

	const refresh = useCallback(async () => {
		setLoading(true);
		setError(null);
		try {
			const json = await getSession();
			const data = json?.data || {};
			setIsAdmin(!!data.is_admin);
			setEmail(data.email || null);
		} catch (e) {
			setIsAdmin(false);
			setEmail(null);
			setError(e?.message || "Failed to load session");
		} finally {
			setLoading(false);
		}
	}, []);

	const login = useCallback(async (emailInput) => {
		setError(null);
		await apiLogin(String(emailInput || "").trim());
		// After login, refresh session to get is_admin/email
		await refresh();
	}, [refresh]);

	const logout = useCallback(async () => {
		setError(null);
		await apiLogout();
		// After logout, refresh session to clear state
		await refresh();
	}, [refresh]);

	useEffect(() => {
		// Load session on mount
		refresh();
	}, [refresh]);

	return useMemo(() => ({
		loading,
		error,
		email,
		isAdmin,
		login,
		logout,
		refresh,
	}), [loading, error, email, isAdmin, login, logout, refresh]);
}
