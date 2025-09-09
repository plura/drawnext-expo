// src/features/admin/pages/UserEdit.jsx
/**
 * Admin → UserEdit (two-column page, left-side form)
 * - Left: user form (half width on md+)
 * - Right: empty (reserved), mirrors DrawingEdit's split layout
 * - Action row (Cancel / Save) with named handlers
 */

import { useEffect, useMemo, useState } from "react";
import { useParams, useNavigate, Link } from "react-router-dom";
import Card from "@/components/cards/Card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { getUser, updateUser } from "@/features/admin/lib/api";

export default function UserEdit() {
  const { id } = useParams();
  const navigate = useNavigate();

  // Server truth
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(null);

  // Form state
  const [email, setEmail] = useState("");
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [isAdmin, setIsAdmin] = useState(false);
  const [isTest, setIsTest] = useState(false);

  // Save UX
  const [saving, setSaving] = useState(false);
  const [saveOk, setSaveOk] = useState(null);
  const [saveError, setSaveError] = useState(null);

  // -------- Load ----------
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);
        setLoadError(null);
        const json = await getUser(id);
        const u = json?.data || null;
        if (!cancelled) {
          setUser(u);
          setEmail(u?.email || "");
          setFirstName(u?.first_name || "");
          setLastName(u?.last_name || "");
          setIsAdmin(!!u?.is_admin);
          setIsTest(!!u?.test);
        }
      } catch (e) {
        if (!cancelled) setLoadError(e?.message || "Failed to load user");
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [id]);

  // -------- Diff ----------
  const changedPayload = useMemo(() => {
    if (!user) return null;
    const patch = { user_id: Number(user.user_id) };
    let dirty = false;

    if ((user.email || "") !== email) { patch.email = email; dirty = true; }
    if ((user.first_name || "") !== firstName) { patch.first_name = firstName; dirty = true; }
    if ((user.last_name || "") !== lastName) { patch.last_name = lastName; dirty = true; }
    if (Boolean(user.is_admin) !== Boolean(isAdmin)) { patch.is_admin = !!isAdmin; dirty = true; }
    if (Boolean(user.test) !== Boolean(isTest)) { patch.test = !!isTest; dirty = true; }

    return dirty ? patch : null;
  }, [user, email, firstName, lastName, isAdmin, isTest]);

  // -------- Handlers ----------
  function handleCancel() {
    navigate("/admin/users");
  }

  async function handleSave() {
    if (!changedPayload) return;
    setSaving(true);
    setSaveOk(null);
    setSaveError(null);
    try {
      const json = await updateUser(changedPayload);
      const updated = json?.data || {};
      // Refresh form from server truth
      setUser(updated);
      setEmail(updated.email || "");
      setFirstName(updated.first_name || "");
      setLastName(updated.last_name || "");
      setIsAdmin(!!updated.is_admin);
      setIsTest(!!updated.test);
      setSaveOk("Saved.");
    } catch (e) {
      setSaveError(e?.details?.email || e?.message || "Save failed");
    } finally {
      setSaving(false);
    }
  }

  // -------- Render ----------
  if (loading) return <div className="p-4">Loading…</div>;
  if (loadError) return <div className="p-4 text-red-600">{loadError}</div>;
  if (!user) return <div className="p-4">User not found.</div>;

  return (
    <div className="p-4 space-y-4">
      {/* Top bar (mirrors DrawingEdit) */}
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold">Edit User</h1>
        <Button variant="outline" asChild>
          <Link to="/admin/users">Back to list</Link>
        </Button>
      </div>

      {/* Two-column page layout (form on the left half) */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* LEFT: User form */}
        <Card className="p-4 space-y-4">
          {/* Email (full row) */}
          <div className="space-y-2">
            <label className="block text-xs text-muted-foreground">Email</label>
            <Input
              type="email"
              autoComplete="email"
              placeholder="user@example.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>

          {/* Names (side-by-side) */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <label className="block text-xs text-muted-foreground">First name</label>
              <Input
                placeholder="(optional)"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <label className="block text-xs text-muted-foreground">Last name</label>
              <Input
                placeholder="(optional)"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
              />
            </div>
          </div>

          {/* Switches row (own row) */}
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2">
              <Switch
                id="user-is-admin"
                checked={isAdmin}
                onCheckedChange={setIsAdmin}
              />
              <label htmlFor="user-is-admin" className="text-sm">Admin</label>
            </div>

            <div className="flex items-center gap-2">
              <Switch
                id="user-is-test"
                checked={isTest}
                onCheckedChange={setIsTest}
              />
              <label htmlFor="user-is-test" className="text-sm">Test user</label>
            </div>
          </div>

          {/* Meta */}
          <div className="pt-1 text-xs text-muted-foreground">
            Created: <span className="font-medium">{user.created_at}</span>
          </div>
        </Card>

        {/* RIGHT: reserved to mirror DrawingEdit's split; intentionally empty for now */}
        <div className="hidden md:block" />
      </div>

      {/* Action row (same pattern as DrawingEdit) */}
      <div className="flex flex-wrap gap-2 pt-2">
        <Button
          variant="outline"
          type="button"
          onClick={handleCancel}
          disabled={saving}
        >
          Cancel
        </Button>

        <Button
          type="button"
          onClick={handleSave}
          disabled={!changedPayload || saving}
        >
          {saving ? "Saving…" : "Save changes"}
        </Button>

        {/* Inline messages */}
        {saveOk && <div className="text-sm text-green-600">{saveOk}</div>}
        {saveError && <div className="text-sm text-red-600">{saveError}</div>}
      </div>
    </div>
  );
}
