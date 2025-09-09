// src/components/layout/AppFullWidthLayout.jsx
import { Outlet } from "react-router-dom";
import Header from "@/components/layout/Header";

export default function AppFullWidthLayout() {
  return (
    <div className="min-h-screen bg-brand flex flex-col">
      <Header />
      {/* edge-to-edge content */}
      <main className="w-full flex-1 min-h-0 flex flex-col">
        <Outlet />
      </main>
    </div>
  );
}
