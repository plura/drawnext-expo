// src/features/admin/components/AdminNav.jsx
/**
 * AdminNav
 * -----------------------------------------
 * Purpose:
 * 	- Simple vertical nav for admin routes.
 * 	- Highlights active route via NavLink.
 */

import NavItem from "@/components/navigation/NavItem"

export default function AdminNav() {
	return (
		<nav className="p-2">
			<ul className="space-y-1">
				<li>
					<NavItem
						to="/admin"
						end
						className="block text-sm px-3 py-2"
						activeClassName="bg-gray-200 font-medium"
					>
						Dashboard
					</NavItem>
				</li>
				<li>
					<NavItem
						to="/admin/drawings"
						className="block text-sm px-3 py-2"
						activeClassName="bg-gray-200 font-medium"
					>
						Drawings
					</NavItem>
				</li>
			</ul>
		</nav>
	)
}
