// src/features/admin/components/AdminGate.jsx
/**
 * AdminGate
 * ---------------------------------------------
 * Purpose:
 * 	- Wrap any admin-only routes/components.
 * 	- Checks the current session via /api/admin/me.
 * 	- If user is an admin → renders children.
 * 	- Otherwise → renders the AdminLoginForm.
 *
 * How it works:
 * 	- On mount, calls getSession() to see if there's a logged-in admin.
 * 	- Exposes an onLogin(email) callback to the login form.
 * 	  That callback calls /api/auth/login and then re-checks the session.
 *
 * Notes:
 * 	- Uses cookie-based PHP session; fetch() includes credentials automatically
 * 	  via our api helper (credentials: 'include').
 * 	- Keep this component high in your /admin route tree so all
 * 	  nested admin pages are protected.
 */

import React from 'react';
import { getSession, login } from '@/features/admin/lib/api';
import AdminLoginForm from './AdminLoginForm';

export default function AdminGate({ children }) {
	const [loading, setLoading] = React.useState(true);
	const [me, setMe] = React.useState(null);		// { email, is_admin } or null
	const [bootstrapError, setBootstrapError] = React.useState(null);

	// Checks the current session; called on mount and after successful login
	const refreshSession = React.useCallback(async () => {
		setLoading(true);
		setBootstrapError(null);
		try {
			const json = await getSession(); // { status:'success', data:{ email, is_admin } }
			setMe(json?.data ?? null);
		} catch (err) {
			// Not authenticated or not admin → we'll show the login form
			setMe(null);
			// You can optionally surface a message here but it's usually
			// nicer to keep it quiet until user attempts to log in.
		} finally {
			setLoading(false);
		}
	}, []);

	React.useEffect(() => {
		refreshSession();
	}, [refreshSession]);

	// Passed to AdminLoginForm. It performs the login and then re-checks session.
	const handleLogin = React.useCallback(async (email) => {
		await login(email);		// throws on error
		await refreshSession();	// if admin, Gate will re-render children
	}, [refreshSession]);

	/* -------- Render states -------- */

	if (loading) {
		return (
			<div className="mx-auto w-full max-w-sm p-6 text-sm text-muted-foreground">
				Checking admin access…
			</div>
		);
	}

	// If the session is present and user is admin → render protected content
	if (me?.is_admin) {
		return <>{children}</>;
	}

	// Otherwise, show the login form
	return (
		<div className="mx-auto w-full max-w-sm p-4">
			<AdminLoginForm
				onLogin={handleLogin}
				// If you want to surface bootstrap errors:
				serverHint={bootstrapError ? String(bootstrapError) : null}
			/>
		</div>
	);
}
