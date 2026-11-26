import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import { adminHeaders } from "../../utils/adminHeaders";
import { accessRoleLabel } from "../../utils/adminDashboard";
import { formError } from "../../utils/formError";
import "./AdminRoles.scss";

const permissionLabels = {
  "bookings.read": "View appointments",
  "bookings.write": "Create, reschedule, and cancel appointments",
  "messages.manage": "Manage contact enquiries",
  "reports.read": "View booking and receipt reports",
  "payments.record": "Record payments",
  "shop.write": "Edit shop profile",
  "shop.manage": "Manage shop branding, services, and schedules",
  "members.invite": "Invite shop staff",
  "members.manage": "Manage shop staff access",
  "payments.void": "Void payment receipts",
};

const actionLabels = {
  "website.owner_initialized": "Website ownership initialized",
  "website.role_changed": "Website role changed",
  "website.shop_access_changed": "Website shop access changed",
  "member.updated": "Shop membership changed",
  "member.removed": "Shop membership revoked",
  "invitation.created": "Shop invitation created",
  "membership.joined": "Shop membership created",
  "platform.access.granted": "Legacy platform access granted",
};

function AccessDetails({ access, shop = false }) {
  if (!access) return "—";
  const parts = [];
  if (Object.hasOwn(access, "role")) {
    parts.push(accessRoleLabel(shop && access.role === "owner" ? "shop_owner" : access.role));
  }
  if (access.suspended) parts.push("Suspended");
  if (access.legacy_platform) parts.push("Legacy platform access");
  if (access.barber_id) parts.push(`Barber profile #${access.barber_id}`);
  if (access.permissions) {
    parts.push(
      access.permissions.length
        ? access.permissions.map((permission) => permissionLabels[permission]).join(", ")
        : "No permissions",
    );
  }
  return parts.join(" · ") || "—";
}

function AccountEditor({ account, data, onSave, busy, onClose }) {
  const [role, setRole] = useState(account.role || "admin");
  const [suspended, setSuspended] = useState(account.suspended);
  const [assignments, setAssignments] = useState(account.assignments);
  const [shopToAdd, setShopToAdd] = useState("");
  const allowed = data.permission_options[role] || [];
  const optionsFor = (shop) => (data.identity.website_role === "owner" ? allowed : shop.delegable);

  function changePermission(shopId, permission, checked) {
    setAssignments((previous) => ({
      ...previous,
      [shopId]: checked
        ? [...previous[shopId], permission]
        : previous[shopId].filter((item) => item !== permission),
    }));
  }

  function removeShop(shopId) {
    setAssignments((previous) =>
      Object.fromEntries(Object.entries(previous).filter(([id]) => id !== String(shopId))),
    );
  }

  const available = data.shop_options.filter(
    (shop) => !Object.hasOwn(assignments, shop.id) && optionsFor(shop).length,
  );
  const payload = Object.entries(assignments).map(([shop, permissions]) => ({
    shop_id: Number(shop),
    permissions: permissions.filter((permission) => allowed.includes(permission)),
  }));
  const invalid =
    (role === "admin" && !payload.length) ||
    (role !== "" && payload.some((assignment) => !assignment.permissions.length));

  return (
    <form
      className="access-editor staff-form"
      onSubmit={(event) => {
        event.preventDefault();
        onSave(account, {
          version: account.version,
          role: role || null,
          suspended: role ? suspended : false,
          assignments: role ? payload : [],
        });
      }}
    >
      <fieldset disabled={busy}>
        <legend>
          {account.name} · {account.email}
        </legend>
        {account.legacy_platform && (
          <p>
            This account has a legacy platform grant. Saving replaces it with the access shown here.
          </p>
        )}
        <label>
          Website role
          <select value={role} onChange={(event) => setRole(event.target.value)}>
            <option value="">Remove website access</option>
            {data.assignable_roles.map((option) => (
              <option key={option} value={option}>
                {accessRoleLabel(option)}
              </option>
            ))}
          </select>
        </label>
        {role && (
          <>
            <label className="access-check">
              <input
                type="checkbox"
                checked={suspended}
                onChange={(event) => setSuspended(event.target.checked)}
              />
              Suspend website access
            </label>
            <h3>Shop assignments</h3>
            <p>
              Booking edits and reports require appointment viewing. Payment recording also requires
              report access.
            </p>
            {data.shop_options
              .filter((shop) => Object.hasOwn(assignments, shop.id))
              .map((shop) => (
                <fieldset key={shop.id} className="access-shop">
                  <legend>{shop.name}</legend>
                  {optionsFor(shop).map((permission) => (
                    <label className="access-check" key={permission}>
                      <input
                        type="checkbox"
                        checked={assignments[shop.id].includes(permission)}
                        onChange={(event) =>
                          changePermission(shop.id, permission, event.target.checked)
                        }
                      />
                      {permissionLabels[permission]}
                    </label>
                  ))}
                  <button
                    className="btn btn-outline"
                    type="button"
                    onClick={() => removeShop(shop.id)}
                  >
                    Remove shop
                  </button>
                </fieldset>
              ))}
            {available.length > 0 && (
              <div className="access-add-shop">
                <label>
                  Add a shop
                  <select value={shopToAdd} onChange={(event) => setShopToAdd(event.target.value)}>
                    <option value="">Choose a shop</option>
                    {available.map((shop) => (
                      <option key={shop.id} value={shop.id}>
                        {shop.name}
                      </option>
                    ))}
                  </select>
                </label>
                <button
                  className="btn btn-outline"
                  type="button"
                  disabled={!shopToAdd}
                  onClick={() => {
                    setAssignments({ ...assignments, [shopToAdd]: [] });
                    setShopToAdd("");
                  }}
                >
                  Add shop
                </button>
              </div>
            )}
            {invalid && (
              <p>
                Choose at least one permission for each assigned shop. Admins need at least one
                shop.
              </p>
            )}
          </>
        )}
        <div className="staff-form-actions">
          <button className="btn btn-primary" disabled={invalid}>
            Save access
          </button>
          <button className="btn btn-outline" type="button" onClick={onClose}>
            Close
          </button>
        </div>
      </fieldset>
    </form>
  );
}

function AccessActivity({ activity, onPage }) {
  return (
    <section className="staff-card access-card">
      <h2>Access activity</h2>
      {activity.data.length === 0 && <p>No permission changes recorded for this scope.</p>}
      <ol className="access-list">
        {activity.data.map((event) => (
          <li key={event.id}>
            <strong>{actionLabels[event.action]}</strong>
            <div>
              {event.actor_name || (event.user_id ? `Account #${event.user_id}` : "Trusted server")}
              {event.shop_name ? ` · ${event.shop_name}` : ""}
              {event.subject_id
                ? ` · ${event.subject_name || `${event.action === "invitation.created" ? "Invitation" : "Account"} #${event.subject_id}`}`
                : ""}
            </div>
            <time dateTime={event.created_at}>{new Date(event.created_at).toLocaleString()}</time>
            {event.details?.before && (
              <p>
                From{" "}
                <AccessDetails
                  access={event.details.before}
                  shop={event.action.startsWith("member.")}
                />{" "}
                to{" "}
                <AccessDetails
                  access={event.details.after}
                  shop={event.action.startsWith("member.")}
                />
                .
              </p>
            )}
            {event.details?.role && (
              <p>
                <AccessDetails access={event.details} shop />
              </p>
            )}
          </li>
        ))}
      </ol>
      {activity.last_page > 1 && (
        <div className="staff-form-actions" aria-label="Access activity pages">
          <button
            className="btn btn-outline"
            type="button"
            disabled={activity.current_page <= 1}
            onClick={() => onPage(activity.current_page - 1)}
          >
            Previous
          </button>
          <span>
            Page {activity.current_page} of {activity.last_page}
          </span>
          <button
            className="btn btn-outline"
            type="button"
            disabled={activity.current_page >= activity.last_page}
            onClick={() => onPage(activity.current_page + 1)}
          >
            Next
          </button>
        </div>
      )}
    </section>
  );
}

export default function AdminRoles() {
  const { session, setSession } = useAdmin();
  const [data, setData] = useState(null);
  const [email, setEmail] = useState("");
  const [account, setAccount] = useState(null);
  const [page, setPage] = useState(1);
  const [refresh, setRefresh] = useState(0);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState("");
  const [error, setError] = useState("");
  const client = useMemo(
    () =>
      axios.create({
        baseURL: `${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/admin/roles`,
        headers: adminHeaders(session),
        timeout: 20000,
      }),
    [session],
  );

  useEffect(() => {
    const controller = new AbortController();
    client
      .get("", { params: { page }, signal: controller.signal })
      .then(({ data }) => setData(data))
      .catch((failure) => {
        if (axios.isCancel(failure)) return;
        if (failure.response?.status === 401) setSession(null);
        else setError(formError(failure));
      });
    return () => controller.abort();
  }, [client, page, refresh, setSession]);

  async function lookup(event) {
    event.preventDefault();
    setBusy(true);
    setError("");
    setNotice("");
    setAccount(null);
    try {
      const { data } = await client.get("/account", { params: { email } });
      setAccount(data.account);
    } catch (failure) {
      if (failure.response?.status === 401) setSession(null);
      else setError(formError(failure));
    } finally {
      setBusy(false);
    }
  }

  async function save(target, payload) {
    setBusy(true);
    setError("");
    setNotice("");
    try {
      await client.put(`/accounts/${target.id}`, payload);
      setAccount(null);
      setEmail("");
      setNotice("Website access saved.");
      setPage(1);
      setRefresh((value) => value + 1);
    } catch (failure) {
      if (failure.response?.status === 401) setSession(null);
      else setError(formError(failure));
      if (failure.response?.status === 409) {
        setAccount(null);
        setRefresh((value) => value + 1);
      }
    } finally {
      setBusy(false);
    }
  }

  const identity = data?.identity;
  const role =
    identity?.website_role || (identity?.role === "owner" ? "shop_owner" : identity?.role);

  return (
    <main className="staff-page roles-page">
      <header className="staff-page-heading">
        <h1 className="section-title">Roles &amp; Permissions</h1>
      </header>
      {error && (
        <p className="admin-error" role="alert">
          {error}
        </p>
      )}
      {notice && <p role="status">{notice}</p>}
      {!data && !error && <p role="status">Loading access…</p>}
      {!data && error && (
        <button
          className="btn btn-outline"
          type="button"
          onClick={() => {
            setError("");
            setRefresh((value) => value + 1);
          }}
        >
          Retry
        </button>
      )}
      {data && (
        <>
          <section className="staff-card access-card">
            <h2>My access</h2>
            <p>
              {accessRoleLabel(role)}
              {identity.shop && ` · ${identity.shop.name}`}
            </p>
            {identity.platform_access && <p>Platform overview and shop status controls</p>}
            {identity.permissions.length > 0 && (
              <ul className="access-permissions">
                {identity.permissions.map((permission) => (
                  <li key={permission}>{permissionLabels[permission]}</li>
                ))}
              </ul>
            )}
            {role === "barber" && <p>Appointment access covers your assigned barber profile.</p>}
            {identity.website_role && !identity.shop && <p>No shop is assigned to this account.</p>}
            {Object.keys(data.my_assignments).length > 0 && (
              <details>
                <summary>Website permissions by shop</summary>
                <ul className="access-list">
                  {Object.entries(data.my_assignments).map(([id, permissions]) => (
                    <li key={id}>
                      <strong>
                        {identity.shops.find((shop) => String(shop.id) === id)?.name ||
                          `Shop #${id}`}
                      </strong>
                      <div>
                        <AccessDetails access={{ permissions }} />
                      </div>
                    </li>
                  ))}
                </ul>
              </details>
            )}
          </section>
          <section className="staff-card access-card">
            <h2>Website roles</h2>
            <dl className="access-roles">
              <dt>Website Owner</dt>
              <dd>Full website and shop access. Manages Super Admin and Admin access.</dd>
              <dt>Super Admin</dt>
              <dd>Oversees shops and manages Admin access within their delegation scope.</dd>
              <dt>Admin</dt>
              <dd>Uses the operational permissions granted for each assigned shop.</dd>
            </dl>
          </section>
          {data.can_manage && (
            <section className="staff-card access-card">
              <h2>Website administrators</h2>
              <form className="staff-form access-add-shop" onSubmit={lookup}>
                <label>
                  Find an existing account by email
                  <input
                    type="email"
                    value={email}
                    maxLength={150}
                    required
                    onChange={(event) => setEmail(event.target.value)}
                  />
                </label>
                <button className="btn btn-outline" disabled={busy}>
                  Find account
                </button>
              </form>
              {account && (
                <AccountEditor
                  key={`${account.id}:${account.version}`}
                  account={account}
                  data={data}
                  busy={busy}
                  onSave={save}
                  onClose={() => setAccount(null)}
                />
              )}
              {data.accounts.length === 0 && (
                <p>No website administrators to manage in your scope.</p>
              )}
              <ul className="access-list">
                {data.accounts.map((item) => (
                  <li className="access-account" key={item.id}>
                    <div>
                      <strong>{item.name}</strong>
                      <div>{item.email}</div>
                      <div>
                        {item.legacy_platform
                          ? "Legacy platform grant"
                          : accessRoleLabel(item.role)}
                        {item.suspended && " · Suspended"}
                      </div>
                    </div>
                    <button
                      className="btn btn-outline"
                      type="button"
                      disabled={busy}
                      aria-label={`Edit access for ${item.name}`}
                      onClick={() => {
                        setError("");
                        setNotice("");
                        setAccount(item);
                      }}
                    >
                      Edit access
                    </button>
                  </li>
                ))}
              </ul>
            </section>
          )}
          {identity.permissions.includes("members.manage") && (
            <section className="staff-card access-card">
              <h2>Shop staff access</h2>
              <div className="staff-form-actions">
                <Link className="btn btn-outline" to="/admin/management?tab=staff">
                  Manage shop staff
                </Link>
                <Link className="btn btn-outline" to="/admin/settings/invite">
                  Invite shop staff
                </Link>
              </div>
            </section>
          )}
          {data.can_view_activity && <AccessActivity activity={data.activity} onPage={setPage} />}
        </>
      )}
    </main>
  );
}
