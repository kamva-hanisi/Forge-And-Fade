import { useState } from "react";
import { Link, NavLink, Navigate, Outlet, useLocation, useNavigate } from "react-router-dom";
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
  Mail,
  SlidersHorizontal,
  Building2,
  Shield,
  Receipt,
} from "lucide-react";
import axios from "axios";
import Brand from "../../components/Brand/Brand";
import { useAdmin } from "../../context/AdminContext";
import { accessRoleLabel, adminDashboard, rolesPath } from "../../utils/adminDashboard";
import { adminHeaders, platformHeaders } from "../../utils/adminHeaders";
import { formError } from "../../utils/formError";
import { shopPath } from "../../utils/shopLinks";
import "./AdminWorkspace.scss";

const items = [
  ["/admin/appointments", "Appointments", CalendarDays],
  ["/admin/payments", "Payments", Receipt],
  ["/admin/inbox", "Contact inbox", Mail],
  ["/admin/management", "Business management", SlidersHorizontal],
  ["/admin/platform", "Platform overview", Building2],
];
const settings = [
  ["/admin/settings/profile", "My profile", UserRound],
  ["/admin/settings/security", "Security", LockKeyhole],
  [rolesPath, "Roles & Permissions", Shield],
  ["/admin/settings/invite", "Invite Member", UserPlus],
];

export default function AdminLayout() {
  const { session, setSession } = useAdmin();
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [expanded, setExpanded] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  if (!session) return <Navigate to="/admin/signin" replace />;
  const isWebsiteOwner = session.user.website_role === "owner";
  const publicBusiness = session.user.shops?.find(
    (shop) => shop.id === session.user.public_shop_id,
  );
  const dashboard = adminDashboard(session.user.role);
  const canReadBookings = session.user.permissions.includes("bookings.read");
  const navigation = [
    ...(canReadBookings ? [[dashboard.path, dashboard.title, LayoutDashboard]] : []),
    ...items,
  ];
  const current = [...navigation, ...settings].find(
    ([path]) =>
      path === pathname || (path === "/admin/appointments" && pathname.startsWith(path + "/")),
  );
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
  const switchShop = async (id) => {
    setError("");
    setBusy(true);
    try {
      const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
      const { data } = isWebsiteOwner
        ? await axios.put(`${apiUrl}/platform/shops/${id}/public-default`, null, {
            headers: platformHeaders(session),
            timeout: 20000,
          })
        : await axios.get(`${apiUrl}/admin/session`, {
            headers: { ...adminHeaders(session), "X-Shop-Id": id },
            timeout: 20000,
          });
      setSession({ token: session.token, user: data.user });
      setOpen(false);
      navigate(
        pathname === "/admin/payments" && data.user.permissions.includes("payments.read")
          ? pathname
          : "/admin",
        { replace: true },
      );
    } catch (failure) {
      if (failure.response?.status === 401) setSession(null);
      else setError(formError(failure));
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
      data-role={session.user.role}
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
        {session.user.shops?.length > 1 && (
          <label className="staff-shop-switcher">
            Active business
            <select
              value={session.user.shop?.id ?? ""}
              onChange={(event) => switchShop(event.target.value)}
              disabled={busy}
            >
              {session.user.shops.map((shop) => (
                <option key={shop.id} value={shop.id}>
                  {shop.name}
                </option>
              ))}
            </select>
          </label>
        )}
        {isWebsiteOwner && session.user.shop && (
          <div className="staff-shop-switcher">
            {publicBusiness && <small>Public site: {publicBusiness.name}</small>}
            {session.user.shop.id !== session.user.public_shop_id && (
              <button
                className="btn btn-outline"
                type="button"
                disabled={busy}
                onClick={() => switchShop(session.user.shop.id)}
              >
                Use this business on the public site
              </button>
            )}
          </div>
        )}
        <nav aria-label="Admin sidebar">
          {navigation
            .filter(
              ([path]) =>
                (path !== "/admin/appointments" || canReadBookings) &&
                (path !== "/admin/payments" ||
                  session.user.permissions.includes("payments.read")) &&
                (path !== "/admin/inbox" ||
                  session.user.permissions?.includes("messages.manage")) &&
                (path !== "/admin/management" ||
                  session.user.permissions?.includes("shop.manage") ||
                  session.user.permissions?.includes("shop.branding.write")) &&
                (path !== "/admin/platform" || session.user.platform_access),
            )
            .map(renderLink)}
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
              {settings
                .filter(
                  ([path]) =>
                    path !== "/admin/settings/invite" ||
                    session.user.permissions?.includes("members.invite"),
                )
                .map(renderLink)}
            </div>
          </div>
        </nav>
        <div className="staff-sidebar-bottom">
          {session.user.shop && (
            <Link
              to={isWebsiteOwner ? "/" : shopPath(session.user.shop.slug)}
              className="staff-site-link"
            >
              View website <ArrowUpRight size={16} aria-hidden="true" />
            </Link>
          )}
          <div className="staff-identity">
            <span className="staff-avatar" aria-hidden="true">
              {session.user.name?.slice(0, 1).toUpperCase()}
            </span>
            <div>
              <strong>{session.user.name}</strong>
              <small>
                {accessRoleLabel(
                  session.user.website_role ||
                    (session.user.role === "owner" ? "shop_owner" : session.user.role),
                )}
                {session.user.shop && ` · ${session.user.shop.name}`}
              </small>
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
            <strong>{current?.[1] || dashboard.title}</strong>
          </span>
          <span className="staff-topbar-name">{session.user.name}</span>
        </div>
        <Outlet
          key={
            pathname === "/admin/platform"
              ? "platform-overview"
              : (session.user.shop?.id ?? "platform")
          }
        />
        {session.user.role === "barber" && (
          <p className="staff-read-only">
            Read-only staff access: only appointments assigned to your barber profile are visible.
          </p>
        )}
      </div>
    </div>
  );
}
