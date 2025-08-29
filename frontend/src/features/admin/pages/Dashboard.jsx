// src/features/admin/pages/Dashboard.jsx
/**
 * Admin → Dashboard
 * -----------------
 * Lightweight landing page for admins. Keeps it simple for now:
 * - Greets the user
 * - Provides quick links into common admin flows
 * You can later evolve this with stats once you add admin endpoints for counts.
 */
import { Link } from "react-router-dom";

export default function Dashboard() {
	return (
		<div className="p-4 space-y-4">
			<h1 className="text-xl font-semibold">Admin Dashboard</h1>
			<p className="text-sm text-muted-foreground">
				Welcome to the admin area. Use the links below to manage content.
			</p>

			<div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
				<Link
					to="/admin/drawings"
					className="rounded-lg border bg-white p-3 hover:bg-gray-50"
				>
					<div className="text-base font-medium">Manage Drawings</div>
					<p className="text-xs text-muted-foreground">
						Browse, filter, and edit drawing submissions.
					</p>
				</Link>

				{/* Future: add Users, Notebooks, Sections, etc. */}
			</div>
		</div>
	);
}
