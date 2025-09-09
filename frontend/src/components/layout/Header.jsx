//components/layout/Header.jsx

import AppLogoBrand from '@/assets/app_logo_brand.svg?react'
import NavItem from '@/components/navigation/NavItem'

export default function Header() {
	return (
		<header className="sticky top-0 z-40 bg-brand/50 backdrop-blur border-b">
			<div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
				<NavItem to="/"><AppLogoBrand className="h-6 w-auto" /></NavItem>
				<nav className="flex items-center gap-1 text-sm">
					<NavItem to="/explore">Explore</NavItem>
					<NavItem to="/wall">Wall</NavItem>
					<NavItem to="/about">About</NavItem>
				</nav>
			</div>
		</header>
	)
}
