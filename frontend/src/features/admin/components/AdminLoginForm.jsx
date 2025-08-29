// src/features/admin/components/AdminLoginForm.jsx
/**
 * AdminLoginForm
 * ---------------------------------------------
 * Purpose:
 * 	- Simple email-only login form for admins.
 * 	- Calls onLogin(email) provided by AdminGate.
 *
 * Props:
 * 	- onLogin(email: string) => Promise<void>
 * 	- serverHint?: string | null  // optional message from the gate
 *
 * Behavior:
 * 	- Local state tracks the email, loading, and any error.
 * 	- On submit, calls onLogin; AdminGate re-checks session and either
 * 	  renders the admin app or leaves the form for another try.
 *
 * Notes:
 * 	- This uses your shared Card component for consistent styling.
 * 	- Relies on shadcn/ui Input, Label, Button (already used elsewhere).
 */

import React from 'react';
import Card from '@/components/cards/Card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';

export default function AdminLoginForm({ onLogin, serverHint = null }) {
	const [email, setEmail] = React.useState('');
	const [submitting, setSubmitting] = React.useState(false);
	const [error, setError] = React.useState(null);

	const handleSubmit = async (e) => {
		e.preventDefault();
		if (!email) {
			setError('Please enter your email.');
			return;
		}
		setSubmitting(true);
		setError(null);
		try {
			await onLogin(String(email).trim());
			// On success, AdminGate will re-render and show the admin app.
		} catch (err) {
			setError(err?.message || 'Login failed. Please try again.');
		} finally {
			setSubmitting(false);
		}
	};

	return (
		<Card className="space-y-4">
			<div>
				<h1 className="text-lg font-semibold">Admin sign in</h1>
				<p className="mt-1 text-xs text-muted-foreground">
					Email-only login for existing admin accounts.
				</p>
				{serverHint && (
					<p className="mt-2 text-xs text-amber-600">
						{serverHint}
					</p>
				)}
			</div>

			<form onSubmit={handleSubmit} className="space-y-3">
				<div>
					<Label htmlFor="admin-email" className="mb-1 block">
						Email
					</Label>
					<Input
						id="admin-email"
						type="email"
						autoComplete="email"
						inputMode="email"
						autoCapitalize="off"
						autoCorrect="off"
						spellCheck={false}
						placeholder="you@example.com"
						value={email}
						onChange={(e) => setEmail(e.target.value)}
						disabled={submitting}
					/>
				</div>

				{error && (
					<p className="text-xs text-red-600">{error}</p>
				)}

				<Button type="submit" className="w-full" disabled={submitting}>
					{submitting ? 'Signing in…' : 'Sign in'}
				</Button>
			</form>

			<p className="text-[11px] text-muted-foreground">
				For production, you can later switch to password or email-link auth. For now,
				this form only accepts emails already present in the database and marked as admin.
			</p>
		</Card>
	);
}
