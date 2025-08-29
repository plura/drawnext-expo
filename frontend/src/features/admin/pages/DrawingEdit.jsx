// src/features/admin/pages/DrawingEdit.jsx
/**
 * Admin → DrawingEdit
 * -------------------
 * Placeholder for the edit form.
 * - Reads :id from the route
 * - You’ll add fetching + form to edit a single drawing once an endpoint exists
 *   (e.g. /api/admin/drawings/view?id=123 and /api/admin/drawings/update).
 */

import { useParams } from "react-router-dom";

export default function DrawingEdit() {
	const { id } = useParams();

	return (
		<div className="p-4 space-y-3">
			<h1 className="text-xl font-semibold">Edit Drawing</h1>
			<p className="text-sm text-muted-foreground">
				Drawing ID: <span className="font-mono">{id}</span>
			</p>

			<div className="rounded-lg border bg-white p-3">
				<p className="text-sm">
					Form goes here. You’ll wire this to an admin endpoint that returns the
					full drawing payload (including neighbors and user).
				</p>
			</div>
		</div>
	);
}
