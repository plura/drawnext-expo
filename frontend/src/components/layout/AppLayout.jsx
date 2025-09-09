// src/components/layout/AppLayout.jsx
import { Outlet } from "react-router-dom";
import Header from "@/components/layout/Header";

export default function AppLayout() {
  return (
    <div className="min-h-screen bg-brand flex flex-col">
      <Header />
      {/* centered content width */}
      <main className="mx-auto w-full p-4 flex-1 min-h-0 flex flex-col max-w-md md:max-w-lg lg:max-w-xl">
        <Outlet />
      </main>
    </div>
  );
}
