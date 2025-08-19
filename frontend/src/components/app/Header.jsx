import NavItem from "./NavItem"

export default function Header() {
	return (
		<header className="sticky top-0 z-40 bg-white/90 backdrop-blur border-b">
			<div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
				<NavItem to="/">DrawNext</NavItem>
				<nav className="flex items-center gap-1 text-sm">
					<NavItem to="/submission">Submit</NavItem>
					<NavItem to="/gallery">Gallery</NavItem>
					<NavItem to="/about">About</NavItem>
				</nav>
			</div>
		</header>
	)
}
