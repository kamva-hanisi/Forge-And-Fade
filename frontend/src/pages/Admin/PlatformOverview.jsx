import { useEffect, useState } from "react";
import { Link, Navigate } from "react-router-dom";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import { platformHeaders } from "../../utils/adminHeaders";
import { shopPath } from "../../utils/shopLinks";
import { formError } from "../../utils/formError";
import "./PlatformOverview.scss";

function ShopStatusControl({ shop, busy, save }) {
  const [open, setOpen] = useState(false);
  const [confirmation, setConfirmation] = useState("");
  const target = shop.status === "active" ? "suspended" : "active";
  const label = target === "suspended" ? "Suspend business" : "Reactivate business";
  if (!open)
    return (
      <button
        type="button"
        className="btn btn-outline"
        disabled={busy}
        onClick={() => setOpen(true)}
      >
        {label}
      </button>
    );
  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        if (confirmation === shop.slug) save(shop, target, confirmation);
      }}
    >
      <p>
        {target === "suspended"
          ? "New online bookings and operational changes will pause. Existing appointments are not cancelled. Customers can still look up and cancel their bookings."
          : "This business will accept online requests again using its saved services, staff and hours."}
      </p>
      <fieldset disabled={busy}>
        <label htmlFor={`confirm-shop-${shop.id}`}>Type {shop.slug} to confirm</label>
        <input
          id={`confirm-shop-${shop.id}`}
          value={confirmation}
          onChange={(event) => setConfirmation(event.target.value)}
          autoComplete="off"
          maxLength={80}
          required
        />
        <button className="btn btn-primary" disabled={busy || confirmation !== shop.slug}>
          Confirm {target === "suspended" ? "suspension" : "reactivation"}
        </button>
        <button
          type="button"
          className="btn btn-outline"
          onClick={() => {
            setOpen(false);
            setConfirmation("");
          }}
        >
          Keep current status
        </button>
      </fieldset>
    </form>
  );
}

export default function PlatformOverview() {
  const { session, setSession } = useAdmin();
  const allowed = session?.user.platform_access;
  const [draft, setDraft] = useState("");
  const [query, setQuery] = useState({ search: "", page: 1, refresh: 0 });
  const [state, setState] = useState({ key: "", data: null, error: "" });
  const [changing, setChanging] = useState(false);
  const [notice, setNotice] = useState("");
  const [actionError, setActionError] = useState("");
  const key = JSON.stringify(query);
  useEffect(() => {
    if (!allowed) return;
    const controller = new AbortController();
    axios
      .get(`${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/platform/overview`, {
        headers: platformHeaders(session),
        params: { search: query.search, page: query.page },
        signal: controller.signal,
        timeout: 20000,
      })
      .then(({ data }) => setState({ key, data, error: "" }))
      .catch((failure) => {
        if (axios.isCancel(failure)) return;
        if (failure.response?.status === 401) setSession(null);
        setState({
          key,
          data: null,
          error:
            failure.response?.status === 403
              ? "Platform access is not available for this account. Return to your business dashboard or sign in again."
              : formError(failure),
        });
      });
    return () => controller.abort();
  }, [allowed, key, query.search, query.page, session, setSession]);
  if (!allowed) return <Navigate to="/admin" replace />;
  const current = state.key === key;
  const data = current ? state.data : null;
  const busy = !current || changing;
  const changeStatus = async (shop, status, confirmSlug) => {
    if (busy) return;
    setChanging(true);
    setNotice("");
    setActionError("");
    try {
      const { data } = await axios.put(
        `${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/platform/shops/${shop.id}/status`,
        { status, revision: shop.lifecycle_revision, confirm_slug: confirmSlug },
        { headers: platformHeaders(session), timeout: 20000 },
      );
      setNotice(data.message);
      setQuery({ ...query, refresh: query.refresh + 1 });
    } catch (failure) {
      if (failure.response?.status === 401) setSession(null);
      setActionError(
        failure.response
          ? formError(failure)
          : "The result could not be confirmed. Refresh the overview before retrying; the business status may already have changed.",
      );
    } finally {
      setChanging(false);
    }
  };
  return (
    <main className="staff-page platform-overview">
      <header className="staff-page-heading">
        <span className="section-label">Platform administration</span>
        <h1 className="section-title">Platform overview</h1>
        <p>View registered businesses and suspend or reactivate their online booking.</p>
      </header>
      <form
        className="platform-overview__search"
        onSubmit={(event) => {
          event.preventDefault();
          if (busy) return;
          setQuery({ search: draft.trim(), page: 1, refresh: query.refresh + 1 });
        }}
      >
        <label htmlFor="platform-shop-search">Find a business by name or identifier</label>
        <input
          id="platform-shop-search"
          type="search"
          value={draft}
          maxLength={100}
          disabled={changing}
          onChange={(event) => setDraft(event.target.value)}
        />
        <button className="btn btn-primary" disabled={busy}>
          Search businesses
        </button>
      </form>
      {busy && (
        <p role="status">{changing ? "Updating business status…" : "Loading platform overview…"}</p>
      )}
      {notice && <p role="status">{notice}</p>}
      {actionError && (
        <div className="admin-error" role="alert">
          <p>{actionError}</p>
          <button
            type="button"
            className="btn btn-outline"
            onClick={() => {
              setActionError("");
              setQuery({ ...query, refresh: query.refresh + 1 });
            }}
          >
            Refresh business statuses
          </button>
        </div>
      )}
      {current && state.error && (
        <div className="admin-error" role="alert">
          <p>{state.error}</p>
          <button
            className="btn btn-outline"
            onClick={() => setQuery({ ...query, refresh: query.refresh + 1 })}
          >
            Retry overview
          </button>
        </div>
      )}
      {data && (
        <>
          <div className="staff-stats platform-overview__stats">
            {[
              ["Registered businesses", data.summary.shops],
              ["Admin-enabled accounts", data.summary.admin_accounts],
              ["Staff memberships", data.summary.memberships],
              ["Bookings · all time", data.summary.bookings],
            ].map(([label, value]) => (
              <section className="staff-card" key={label}>
                <p>{label}</p>
                <strong>{value}</strong>
              </section>
            ))}
          </div>
          <p>
            Totals cover the entire platform, not just search results. Memberships exclude revoked
            roles and are not active login sessions. Booking counts do not prove attendance,
            payments or revenue.
          </p>
          <h2>Registered businesses</h2>
          {!data.shops.data.length && <p role="status">No businesses match this search.</p>}
          <div className="platform-overview__shops">
            {data.shops.data.map((shop) => (
              <article className="staff-card" key={shop.id}>
                <h3>{shop.name}</h3>
                <p className="platform-overview__slug">{shop.slug}</p>
                <p>
                  Business status: <strong>{shop.status}</strong>
                </p>
                <dl>
                  {[
                    ["Bookings · all time", shop.booking_count],
                    ["Confirmed · all time", shop.confirmed_count],
                    ["Staff memberships", shop.membership_count],
                    ["Active services", shop.active_services],
                    ["Active barbers", shop.active_barbers],
                  ].map(([label, value]) => (
                    <div key={label}>
                      <dt>{label}</dt>
                      <dd>{value}</dd>
                    </div>
                  ))}
                </dl>
                <Link to={shopPath(shop.slug)}>View public booking page</Link>
                <ShopStatusControl
                  key={`${shop.id}-${shop.lifecycle_revision}`}
                  shop={shop}
                  busy={busy}
                  save={changeStatus}
                />
              </article>
            ))}
          </div>
          <nav className="platform-overview__pagination" aria-label="Platform business pages">
            <button
              className="btn btn-outline"
              disabled={busy || data.shops.current_page <= 1}
              onClick={() => setQuery({ ...query, page: query.page - 1 })}
            >
              Previous page
            </button>
            <span>
              Page {data.shops.current_page} of {data.shops.last_page} · {data.shops.total} matching
              businesses
            </span>
            <button
              className="btn btn-outline"
              disabled={busy || data.shops.current_page >= data.shops.last_page}
              onClick={() => setQuery({ ...query, page: query.page + 1 })}
            >
              Next page
            </button>
          </nav>
        </>
      )}
    </main>
  );
}
