// src/features/admin/pages/UserEdit.jsx
import { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import Card from "@/components/cards/Card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import AppAlert from "@/components/feedback/AppAlert";
import { getUser } from "@/features/admin/lib/api";

export default function UserEdit() {
  const { id } = useParams();
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);
        setError(null);
        const json = await getUser(id);
        if (!cancelled) setUser(json?.data || null);
      } catch (e) {
        if (!cancelled) setError(e?.message || "Failed to load user");
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [id]);

  if (loading) return <div className="p-4">Loading…</div>;
  if (error) return <AppAlert variant="destructive" title="Error">{error}</AppAlert>;
  if (!user) return <div className="p-4">User not found.</div>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">User</h1>
        <Button variant="outline" asChild>
          <Link to="/admin/users">Back to list</Link>
        </Button>
      </div>

      <Card className="p-4 space-y-2">
        <div className="text-sm">
          <div className="text-muted-foreground">Email</div>
          <div className="font-medium break-all">{user.email}</div>
        </div>

        <div className="text-sm">
          <div className="text-muted-foreground">Role</div>
          <div className="font-medium">
            {Number(user.is_admin) === 1 ? (
              <Badge>Admin</Badge>
            ) : (
              <Badge variant="secondary">User</Badge>
            )}
          </div>
        </div>

        <div className="text-sm">
          <div className="text-muted-foreground">Created</div>
          <div className="font-medium">{user.created_at}</div>
        </div>
      </Card>

      {/* Future: recent drawings, actions, etc. */}
    </div>
  );
}
