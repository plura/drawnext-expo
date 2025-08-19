// src/features/home/Home.jsx
export default function Home() {
	return (
		<div className="space-y-6">
			<h1 className="text-2xl font-bold">Welcome to DrawNext</h1>
			<p className="text-muted-foreground">
				This is a collaborative drawing experiment. Choose a section of a notebook, 
				add your drawing, and see how it connects with others.
			</p>
			<p className="text-sm text-gray-500">
				Use the menu above to start submitting or explore the gallery.
			</p>
		</div>
	)
}
