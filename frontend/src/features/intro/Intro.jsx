// src/features/intro/Intro.jsx
import { Link } from "react-router-dom";

export default function Intro() {
	return (
		<div className="space-y-6">
			<h1 className="text-2xl md:text-3xl font-bold leading-tight">
				DrawNext
			</h1>
			<p className="text-muted-foreground">
				A collaborative drawing experiment inspired by the Exquisite Corpse game. Draw in a
				physical notebook, snap a photo, and connect your page with others.
			</p>
			<div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
				<Link
					to="/submit"
					className="inline-flex items-center justify-center rounded-lg bg-blue-600 text-white px-4 py-2 font-medium hover:bg-blue-700"
				>
					Start a submission
				</Link>
				<Link
					to="/gallery"
					className="inline-flex items-center justify-center rounded-lg border px-4 py-2 font-medium hover:bg-gray-50"
				>
					View the wall
				</Link>
			</div>
		</div>
	);
}
