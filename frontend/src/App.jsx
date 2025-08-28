// src/App.jsx
import React from "react"
import { BrowserRouter, Routes, Route, Outlet, useLocation } from "react-router-dom"
import { ConfigProvider } from "./app/ConfigProvider.jsx"
import Header from "./components/layout/Header.jsx"
import { cn } from "@/lib/utils"

// pages
import Intro from "./features/intro/Intro.jsx"
import Gallery from "./features/gallery/Gallery.jsx";
import Submission from "./features/submit/Submission.jsx"
import About from "./features/about/About.jsx"
import Test from "./features/test/Test.jsx"

function AppLayout() {
	const location = useLocation();
	const FULL_WIDTH_PREFIXES = ["/test", "/gallery"];
	const isFullWidth = FULL_WIDTH_PREFIXES.some((p) =>
		location.pathname.startsWith(p)
	);

	return (
		<div className="md:min-h-screen min-h-dvh bg-gray-50 flex flex-col">
			<Header />
			<main
				className={cn(
					"mx-auto w-full p-4 flex-1 min-h-0 flex flex-col",
					isFullWidth ? "max-w-none" : "max-w-md md:max-w-lg lg:max-w-xl"
				)}
			>
				<Outlet />
			</main>
		</div>
	);
}

export default function App() {
  return (
    <ConfigProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<AppLayout />}> 
            <Route path="/" element={<Intro />} />
			<Route path="/gallery" element={<Gallery />} />
            <Route path="/about" element={<About />} />
            <Route path="/submit" element={<Submission />} />
            {/* Developer-only route (not in header) */}
            <Route path="/test" element={<Test />} />
            <Route path="*" element={<p>Page not found</p>} />
          </Route>
        </Routes>
      </BrowserRouter>
    </ConfigProvider>
  )
}
