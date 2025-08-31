// src/App.jsx
import React from "react";
import { BrowserRouter, Routes, Route, Outlet, useLocation } from "react-router-dom";
import { ConfigProvider } from "./app/ConfigProvider.jsx";
import Header from "./components/layout/Header.jsx";
import { cn } from "@/lib/utils";

// public pages
import Intro from "./features/intro/Intro.jsx";
import Gallery from "./features/gallery/Gallery.jsx";
import Submission from "./features/submit/Submission.jsx";
import About from "./features/about/About.jsx";
import Test from "./features/test/Test.jsx";

// admin
import AdminGate from "@/features/admin/components/AdminGate";
import AdminLayout from "@/features/admin/components/AdminLayout";
import Dashboard from "@/features/admin/pages/Dashboard";
import DrawingsList from "@/features/admin/pages/DrawingsList";
import DrawingEdit from "@/features/admin/pages/DrawingEdit";
import UsersList from "@/features/admin/pages/UsersList";
import UserEdit from "@/features/admin/pages/UserEdit";

// ...
<Route path="/admin" element={<AdminGate><AdminLayout /></AdminGate>}>
  <Route index element={<Dashboard />} />
  <Route path="drawings" element={<DrawingsList />} />
  <Route path="drawings/:id" element={<DrawingEdit />} />

  {/* NEW */}
  <Route path="users" element={<UsersList />} />
  <Route path="users/:id" element={<UserEdit />} />
</Route>


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
          {/* Public app */}
          <Route path="/" element={<AppLayout />}>
            <Route index element={<Intro />} />
            <Route path="about" element={<About />} />
            <Route path="submit" element={<Submission />} />
            <Route path="gallery" element={<Gallery />} />
            <Route path="test" element={<Test />} />
            <Route path="*" element={<p>Page not found</p>} />
          </Route>

          {/* Admin app — protected by AdminGate, wrapped in AdminLayout */}
          <Route
            path="/admin"
            element={
              <AdminGate>
                <AdminLayout />
              </AdminGate>
            }
          >
            <Route index element={<Dashboard />} />
            <Route path="drawings" element={<DrawingsList />} />
            <Route path="drawings/:id" element={<DrawingEdit />} />
			<Route path="users" element={<UsersList />} />
  			<Route path="users/:id" element={<UserEdit />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </ConfigProvider>
  );
}
