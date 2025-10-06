// src/components/layout/AppLayout.jsx
import { Outlet } from "react-router-dom";
import Header from "@/components/layout/Header";

export default function AppLayout() {
  return (
    <div className="min-h-screen bg-brand flex flex-col">
      <Header />
      {/* centered content width */}
      <main className="flex flex-col w-full max-w-md md:max-w-lg lg:max-w-xl mx-auto h-[calc(100dvh-var(--dn-header-height))] p-4">
        <Outlet />
      </main>
    </div>
  );
}
