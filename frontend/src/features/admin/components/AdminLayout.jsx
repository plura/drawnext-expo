// src/features/admin/components/AdminLayout.jsx
import { Outlet, NavLink } from "react-router-dom";
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar";
import { Button } from "@/components/ui/button";
import { useAdminAuth } from "../lib/useAdminAuth";

// Optional: import icons from lucide-react
import { LayoutDashboard, Images, Users } from "lucide-react";

export default function AdminLayout() {
  const { logout, refresh, me } = useAdminAuth();

  const navItems = [
    { to: "/admin", label: "Dashboard", icon: LayoutDashboard },
    { to: "/admin/drawings", label: "Drawings", icon: Images },
    { to: "/admin/users", label: "Users", icon: Users },
  ];

  return (
    <SidebarProvider>
      <div className="flex min-h-screen w-full">
        {/* Sidebar */}
        <Sidebar>
          <SidebarHeader className="px-3 py-3">
            <div className="font-semibold">DrawNext Admin</div>
            {me?.email && (
              <div className="mt-0.5 text-xs text-muted-foreground truncate">
                {me.email}
              </div>
            )}
          </SidebarHeader>
          <SidebarContent>
            <SidebarGroup>
              <SidebarGroupLabel>Admin</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  {navItems.map((item) => (
                    <SidebarMenuItem key={item.to}>
                      <SidebarMenuButton asChild>
                        <NavLink
                          to={item.to}
                          end={item.to === "/admin"}
                          className={({ isActive }) =>
                            [
                              "flex items-center gap-2",
                              isActive
                                ? "text-foreground font-medium"
                                : "text-muted-foreground",
                            ].join(" ")
                          }
                        >
                          {item.icon && (
                            <item.icon className="h-4 w-4 shrink-0" />
                          )}
                          <span>{item.label}</span>
                        </NavLink>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  ))}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          </SidebarContent>
        </Sidebar>

        {/* Main content */}
        <div className="flex flex-1 flex-col">
          <header className="flex items-center justify-between border-b bg-white px-4 py-2">
            <div className="flex items-center gap-2">
              <SidebarTrigger />
              <h1 className="text-base font-semibold">Admin</h1>
            </div>

            <div className="flex items-center gap-2">
              {me?.email && (
                <span className="text-sm text-muted-foreground">
                  {me.email}
                </span>
              )}
              <Button
                variant="outline"
                size="sm"
                onClick={async () => {
                  await logout();
                  await refresh();
                }}
              >
                Logout
              </Button>
            </div>
          </header>

          <main className="flex-1 p-4">
            <Outlet />
          </main>
        </div>
      </div>
    </SidebarProvider>
  );
}
