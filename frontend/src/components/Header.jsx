// src/components/Header.jsx
import React from "react";

export default function Header() {
	return (
		<header className="bg-white border-b shadow-sm">
			<div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
				{/* Logo */}
				<button className="text-lg font-bold text-gray-800 hover:opacity-80">
					DrawNext
				</button>

				{/* Nav */}
				<nav className="space-x-6 text-sm font-medium text-gray-700">
					<a href="/submission" className="hover:text-blue-600">
						Submit
					</a>
					<a href="/gallery" className="hover:text-blue-600">
						Gallery
					</a>
					<a href="/about" className="hover:text-blue-600">
						About
					</a>
				</nav>
			</div>
		</header>
	);
}
