// src/components/layout/HeadlessLayout.jsx
import { Outlet } from "react-router-dom";

export default function HeadlessLayout() {
  return (
    // fullscreen, no header
    <div className="min-h-screen bg-brand">
      <Outlet />
    </div>
  );
}
