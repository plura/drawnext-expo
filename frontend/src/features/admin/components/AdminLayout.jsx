// src/features/admin/components/AdminLayout.jsx
/**
 * AdminLayout
 * -----------------------------------------
 * Purpose:
 * 	- Shell for the admin area: sidebar nav + header + main outlet.
 * 	- Includes a Logout action wired to useAdminAuth().
 *
 * Usage:
 * 	<Route path="/admin" element={<AdminGate><AdminLayout /></AdminGate>}>
 * 		<Route index element={<Dashboard />} />
 * 		<Route path="drawings" element={<DrawingsList />} />
 * 		<Route path="drawings/:id" element={<DrawingEdit />} />
 * 	</Route>
 */

import { Outlet, Link } from "react-router-dom";
import AdminNav from "./AdminNav";
import { Button } from "@/components/ui/button";
import { useAdminAuth } from "../lib/useAdminAuth";

export default function AdminLayout() {
	const { logout, refresh, me } = useAdminAuth();

	return (
		<div className="min-h-screen grid grid-cols-1 md:grid-cols-[240px,1fr]">
			{/* Sidebar */}
			<aside className="border-b md:border-b-0 md:border-r bg-white">
                {/* Brand */}
				<div className="px-4 py-3 border-b">
					<Link to="/admin" className="font-semibold">
						DrawNext Admin
					</Link>
					{me?.email && (
						<div className="mt-0.5 text-xs text-muted-foreground">
							{me.email}
						</div>
					)}
				</div>

				<AdminNav />
			</aside>

			{/* Main column */}
			<div className="flex min-h-dvh flex-col">
				{/* Top bar */}
				<header className="sticky top-0 z-10 border-b bg-white/90 backdrop-blur">
					<div className="flex items-center justify-between px-4 py-2">
						<h1 className="text-base font-semibold">Admin</h1>
						<div className="flex items-center gap-2">
							<Button
								variant="outline"
								size="sm"
								onClick={async () => {
									await logout();
									await refresh();
								}}
							>
								Logout
							</Button>
						</div>
					</div>
				</header>

				{/* Routed content */}
				<main className="p-4">
					<Outlet />
				</main>
			</div>
		</div>
	);
}
