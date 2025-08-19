// src/App.jsx
import React from "react"
import { BrowserRouter, Routes, Route } from "react-router-dom"
import { ConfigProvider } from "./app/ConfigProvider.jsx"
import Header from "./components/app/Header.jsx"

// pages
import Home from "./features/home/Home.jsx"
import Submission from "./features/submission/Submission.jsx"
/* import Gallery from "./features/gallery/Gallery.jsx"
import About from "./features/about/About.jsx" */

export default function App() {
	return (
		<ConfigProvider>
			<BrowserRouter>
				<div className="md:min-h-screen min-h-dvh bg-gray-50 flex flex-col">
					<Header />
					<main className="mx-auto w-full max-w-md md:max-w-lg lg:max-w-xl p-4 flex-1 min-h-0 flex flex-col">
						<Routes>
							<Route path="/" element={<Home />} />
							<Route path="/submission" element={<Submission />} />
{/* 							<Route path="/gallery" element={<Gallery />} />
							<Route path="/about" element={<About />} /> */}
							<Route path="*" element={<p>Page not found</p>} />
						</Routes>
					</main>
				</div>
			</BrowserRouter>
		</ConfigProvider>
	)
}
