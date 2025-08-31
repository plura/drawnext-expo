// src/features/admin/pages/Dashboard.jsx
/**
 * Admin → Dashboard
 * -----------------
 * Landing page with quick links into admin flows.
 * Uses your Card component for consistent styling.
 */

import { Link } from "react-router-dom";
import Card from "@/components/cards/Card";

export default function Dashboard() {
	return (
		<div className="p-4 space-y-4">
			<h1 className="text-xl font-semibold">Admin Dashboard</h1>
			<p className="text-sm text-muted-foreground">
				Welcome to the admin area. Use the links below to manage content.
			</p>

			<div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
				<Link to="/admin/drawings">
					<Card className="hover:bg-gray-50 transition-colors">
						<div className="text-base font-medium">Manage Drawings</div>
						<p className="text-xs text-muted-foreground">
							Browse, filter, and edit drawing submissions.
						</p>
					</Card>
				</Link>

				<Link to="/admin/users">
					<Card className="hover:bg-gray-50 transition-colors">
						<div className="text-base font-medium">Manage Users</div>
						<p className="text-xs text-muted-foreground">
							View and manage registered user accounts.
						</p>
					</Card>
				</Link>
			</div>
		</div>
	);
}
