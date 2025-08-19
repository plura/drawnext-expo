// src/components/NavItem.jsx
import { NavLink } from "react-router-dom"

export default function NavItem({ to, children }) {
	return (
		<NavLink
			to={to}
			className={({ isActive }) =>
				`rounded-md px-3 py-1.5 hover:bg-gray-100 ${
					isActive ? "bg-gray-200 font-medium" : ""
				}`
			}
		>
			{children}
		</NavLink>
	)
}
