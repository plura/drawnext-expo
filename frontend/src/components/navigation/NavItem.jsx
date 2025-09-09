// src/components/navigation/NavItem.jsx
import { NavLink } from "react-router-dom"
import { cn } from "@/lib/utils"

export default function NavItem({
	to,
	children,
	className,
	activeClassName,
	end = false,
}) {
	return (
		<NavLink
			to={to}
			end={end}
			className={({ isActive }) =>
				cn(
					"rounded-md px-3 py-1.5 hover:bg-brand bg-brand/75",
					className,
					isActive && cn("bg-brand font-medium", activeClassName)
				)
			}
		>
			{children}
		</NavLink>
	)
}
