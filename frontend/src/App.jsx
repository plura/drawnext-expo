// src/App.jsx
import { Routes, Route } from "react-router-dom";

// Public layouts
import AppLayout from "@/components/layout/AppLayout";
import AppFullWidthLayout from "@/components/layout/AppFullWidthLayout";
import HeadlessLayout from "@/components/layout/HeadlessLayout";

// Public pages
import About from "@/features/about/About.jsx";
import Submission from "@/features/submit/Submission.jsx";
import Explore from "@/features/explore/Explore.jsx"; // full-width with header
import IntroWall from "@/features/home/IntroWall.jsx"; // headless
import ShowWall from "@/features/show/ShowWall.jsx"; // headless
import RelationsPage from "@/features/relations/RelationsPage"; // full-width with header
import Wall from "@/features/wall/Wall.jsx"; // headless

// Admin
import AdminLayout from "@/features/admin/components/AdminLayout.jsx";
import AdminGate from "@/features/admin/components/AdminGate.jsx";
import Dashboard from "@/features/admin/pages/Dashboard.jsx";
import DrawingsList from "@/features/admin/pages/DrawingsList.jsx";
import DrawingEdit from "@/features/admin/pages/DrawingEdit.jsx";
import UsersList from "@/features/admin/pages/UsersList.jsx";
import UserEdit from "@/features/admin/pages/UserEdit.jsx";

export default function App() {
  return (
    <Routes>
      {/* Public: header + centered container */}
      <Route element={<AppLayout />}>
        <Route path="about" element={<About />} />
        <Route path="submit" element={<Submission />} />
      </Route>

      {/* Public: header + full-width */}
      <Route element={<AppFullWidthLayout />}>
        <Route path="explore" element={<Explore />} />
        <Route path="wall" element={<Wall />} />
        <Route path="relations/:id" element={<RelationsPage />} />
      </Route>

      {/* Public: headless fullscreen */}
      <Route element={<HeadlessLayout />}>
        <Route path="/" element={<IntroWall />} />
        <Route path="show" element={<ShowWall />} />
      </Route>

      {/* Admin (wrapped & protected) */}
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

      {/* 404 */}
      <Route path="*" element={<p className="p-6">Page not found</p>} />
    </Routes>
  );
}
