import { useState } from "react";
import { Link, NavLink, Navigate, Outlet, useLocation } from "react-router-dom";
import {
  CalendarDays,
  LayoutDashboard,
  Settings,
  UserRound,
  LockKeyhole,
  UserPlus,
  LogOut,
  Menu,
  X,
  ArrowUpRight,
  ChevronDown,
} from "lucide-react";
import axios from "axios";
import Brand from "../../components/Brand/Brand";
import { useAdmin } from "../../context/AdminContext";
import "./AdminWorkspace.scss";

const items = [
  ["/admin", "Dashboard", LayoutDashboard],
  ["/admin/appointments", "Appointments", CalendarDays],
];
const settings = [
  ["/admin/settings/profile", "My profile", UserRound],
  ["/admin/settings/security", "Security", LockKeyhole],
  ["/admin/settings/invite", "Invite Member", UserPlus],
];

export default function AdminLayout() {
  const { session, setSession } = useAdmin();
  const { pathname } = useLocation();
  const [open, setOpen] = useState(false);
  const [expanded, setExpanded] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  if (!session) return <Navigate to="/admin/signin" replace />;
  const current = [...items, ...settings].find(([path]) => path === pathname);
  const logout = async () => {
    setBusy(true);
    setError("");
    try {
      await axios.post(
        `${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/admin/logout`,
        {},
        { headers: { Authorization: `Bearer ${session.token}` }, timeout: 20000 },
      );
      setSession(null);
    } catch (err) {
      if (err.response?.status === 401) setSession(null);
      else setError("Unable to sign out. Check your connection and retry.");
    } finally {
      setBusy(false);
    }
  };
  const renderLink = ([path, label, Icon]) => (
    <NavLink key={path} end to={path} onClick={() => setOpen(false)}>
      <Icon size={19} aria-hidden="true" />
      <span>{label}</span>
    </NavLink>
  );
  return (
    <div
      className="staff-layout"
      onKeyDown={(event) => {
        if (event.key === "Escape") setOpen(false);
      }}
    >
      <header className="staff-mobile-header">
        <Brand />
        <button
          className="icon-button"
          type="button"
          aria-label={open ? "Close admin menu" : "Open admin menu"}
          aria-expanded={open}
          aria-controls="staff-sidebar"
          onClick={() => setOpen(!open)}
        >
          {open ? <X /> : <Menu />}
        </button>
      </header>
      <aside id="staff-sidebar" className={`staff-sidebar${open ? " is-open" : ""}`}>
        <div className="staff-brand">
          <Brand />
          <span className="section-label">Administration</span>
        </div>
        <nav aria-label="Admin sidebar">
          {items.map(renderLink)}
          <div className="staff-settings">
            <button
              type="button"
              aria-expanded={expanded}
              aria-controls="staff-settings-links"
              onClick={() => setExpanded(!expanded)}
            >
              <Settings size={19} aria-hidden="true" />
              <span>Settings</span>
              <ChevronDown size={16} aria-hidden="true" className={expanded ? "is-expanded" : ""} />
            </button>
            <div id="staff-settings-links" hidden={!expanded}>
              {settings.map(renderLink)}
            </div>
          </div>
        </nav>
        <div className="staff-sidebar-bottom">
          <Link to="/" className="staff-site-link">
            View website <ArrowUpRight size={16} aria-hidden="true" />
          </Link>
          <div className="staff-identity">
            <span className="staff-avatar" aria-hidden="true">
              {session.user.name?.slice(0, 1).toUpperCase()}
            </span>
            <div>
              <strong>{session.user.name}</strong>
              <small>Administrator</small>
            </div>
          </div>
          <button type="button" onClick={logout} disabled={busy}>
            <LogOut size={18} aria-hidden="true" />
            {busy ? "Signing out…" : "Sign out"}
          </button>
          {error && <p role="alert">{error}</p>}
        </div>
      </aside>
      {open && (
        <button
          type="button"
          className="staff-backdrop"
          aria-label="Dismiss admin menu"
          onClick={() => setOpen(false)}
        />
      )}
      <div className="staff-main">
        <div className="staff-topbar">
          <span>
            {pathname.includes("/settings/") ? "Settings / " : "Administration / "}
            <strong>{current?.[1] || "Dashboard"}</strong>
          </span>
          <span className="staff-topbar-name">{session.user.name}</span>
        </div>
        <Outlet />
      </div>
    </div>
  );
}
