// src/App.jsx
import React from "react";
import {
  BrowserRouter,
  Routes,
  Route,
  Outlet,
  useLocation,
} from "react-router-dom";
import { ConfigProvider } from "./app/ConfigProvider.jsx";
import Header from "./components/layout/Header.jsx";
import { cn } from "@/lib/utils";

// pages
import Intro from "./features/intro/Intro.jsx";
import Gallery from "./features/gallery/Gallery.jsx";
import Submission from "./features/submit/Submission.jsx";
import About from "./features/about/About.jsx";
import Test from "./features/test/Test.jsx";

//pages: admin
import AdminGate from "@/features/admin/components/AdminGate"
import AdminLayout from "@/features/admin/components/AdminLayout"
import Dashboard from "@/features/admin/pages/Dashboard"
import DrawingsList from "@/features/admin/pages/DrawingsList"
import DrawingEdit from "@/features/admin/pages/DrawingEdit"


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
					{/* Public app (with Header) */}
					<Route path="/" element={<AppLayout />}>
						<Route index element={<Intro />} />
						<Route path="about" element={<About />} />
						<Route path="submit" element={<Submission />} />
						<Route path="gallery" element={<Gallery />} />
						<Route path="test" element={<Test />} />
						<Route path="*" element={<p>Page not found</p>} />
					</Route>

					{/* Admin app (NO public Header) */}
					<Route
						path="/admin"
						element={
							<AdminGate>
								 {/* AdminLayout renders the admin shell (sidebar/nav) and an <Outlet /> */}
								<AdminLayout />
							</AdminGate>
						}
					>	
						 {/* /admin */}
						<Route index element={<Dashboard />} />
						 {/* /admin/drawings */}
						<Route path="drawings" element={<DrawingsList />} />
						 {/* /admin/drawing */}
						<Route path="drawings/:id" element={<DrawingEdit />} />
					</Route>
				</Routes>
			</BrowserRouter>
		</ConfigProvider>
	);
}
