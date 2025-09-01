// src/features/admin/pages/UsersList.jsx
import { useEffect, useState, useMemo } from "react";
import { Link } from "react-router-dom";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import AppAlert from "@/components/feedback/AppAlert";
import Card from "@/components/cards/Card";
import { listUsers } from "@/features/admin/lib/api";
import { Badge } from "@/components/ui/badge";

const PAGE_SIZE = 24;

// helper: name only; blank if none
function displayName(u) {
  const fn = (u.first_name || "").trim();
  const ln = (u.last_name || "").trim();
  return fn || ln ? [fn, ln].filter(Boolean).join(" ") : "";
}

export default function UsersList() {
  const [rows, setRows] = useState([]);
  const [q, setQ] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [offset, setOffset] = useState(0);
  const [hasMore, setHasMore] = useState(false);

  async function fetchPage(nextOffset = 0) {
    setLoading(true);
    setError(null);
    try {
      const json = await listUsers({ limit: PAGE_SIZE, offset: nextOffset, q });
      const data = Array.isArray(json?.data) ? json.data : [];
      const meta = json?.meta || {};
      setRows((prev) => (nextOffset === 0 ? data : [...prev, ...data]));
      setHasMore(!!meta?.has_more);
      setOffset(Number(meta?.next_offset || 0));
    } catch (e) {
      setError(e?.message || "Failed to load users");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchPage(0);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const t = setTimeout(() => fetchPage(0), 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q]);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Users</h1>
      </div>

      {/* Filters */}
      <Card className="p-3">
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex-1 min-w-[260px]">
            <label className="block text-xs text-muted-foreground mb-1">
              Search name or email
            </label>
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="e.g. Sato / sato@example.com"
            />
          </div>

          <Button
            variant="outline"
            type="button"
            onClick={() => {
              setQ("");
              fetchPage(0);
            }}
          >
            Clear
          </Button>
        </div>
      </Card>

      {error && (
        <AppAlert variant="destructive" title="Failed to load users">
          {error}
        </AppAlert>
      )}

      {/* Table */}
      <Card className="p-0 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                  Name
                </th>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                  Email
                </th>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                  Admin
                </th>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                  Created
                </th>
                <th className="px-3 py-2 text-left font-medium text-muted-foreground">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && !loading ? (
                <tr>
                  <td
                    colSpan={5}
                    className="px-3 py-8 text-center text-muted-foreground"
                  >
                    No users found.
                  </td>
                </tr>
              ) : (
                rows.map((u) => (
                  <tr key={u.user_id} className="border-t">
                    <td className="px-3 py-2">{displayName(u)}</td>
                    <td className="px-3 py-2 break-all">{u.email}</td>
                    <td className="px-3 py-2">
                      {u.is_admin ? (
                        <Badge variant="default">Admin</Badge>
                      ) : (
                        <Badge variant="secondary">User</Badge>
                      )}
                    </td>
                    <td className="px-3 py-2">{u.created_at}</td>
                    <td className="px-3 py-2">
                      <Button asChild variant="outline" size="sm">
                        <Link to={`/admin/users/${u.user_id}`}>View</Link>
                      </Button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {hasMore && (
          <div className="p-3 border-t">
            <Button onClick={() => fetchPage(offset)} disabled={loading}>
              {loading ? "Loading…" : "Load more"}
            </Button>
          </div>
        )}
      </Card>
    </div>
  );
}
