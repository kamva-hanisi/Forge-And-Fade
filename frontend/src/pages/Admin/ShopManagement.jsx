import { useEffect, useMemo, useState } from "react";
import { Link, Navigate, useSearchParams } from "react-router-dom";
import axios from "axios";
import { adminHeaders } from "../../utils/adminHeaders";
import { useAdmin } from "../../context/AdminContext";
import { formError } from "../../utils/formError";
import { catalogReadiness } from "../../utils/shopOnboarding";
import { shopPath } from "../../utils/shopLinks";
import { BrandingEditor, MediaEditor } from "./ShopMediaEditor";
import BrandingGenerator from "./BrandingGenerator";
import ScheduleEditor from "./ScheduleEditor";
import "./ShopManagement.scss";

const managementTabs = [
  ["staff", "Staff", "shop.manage"],
  ["services", "Services", "shop.manage"],
  ["branding", "Branding", "shop.branding.write"],
  ["portfolios", "Barber portfolios", "shop.manage"],
  ["hours", "Business hours", "shop.hours.write"],
  ["schedules", "Barber schedules", "shop.manage"],
];

function CatalogEditor({ item, type, busy, save }) {
  const [draft, setDraft] = useState({ name: "", price: "", duration: 30, active: true, ...item });
  const [confirm, setConfirm] = useState(false);
  const change = (event) =>
    setDraft({
      ...draft,
      [event.target.name]:
        event.target.type === "checkbox" ? event.target.checked : event.target.value,
    });
  return (
    <form
      className="staff-card staff-form"
      onSubmit={(event) => {
        event.preventDefault();
        if (item?.active && !draft.active && !confirm) {
          setConfirm(true);
          return;
        }
        save(
          `/management/${type}${item?.id ? `/${item.id}` : ""}`,
          draft,
          item?.id ? "put" : "post",
        );
      }}
    >
      <h2>{item ? item.name : `Add ${type === "services" ? "service" : "barber profile"}`}</h2>
      <fieldset className="staff-booking-fields" disabled={busy}>
        <label>
          Name
          <input name="name" required maxLength={100} value={draft.name} onChange={change} />
        </label>
        {type === "services" && (
          <>
            <label>
              Price (ZAR)
              <input
                name="price"
                type="number"
                step="0.01"
                min={0}
                max={99999999.99}
                required
                value={draft.price}
                onChange={change}
              />
            </label>
            <label>
              Duration (minutes)
              <input
                name="duration"
                type="number"
                min={10}
                max={480}
                step={1}
                required
                value={draft.duration}
                onChange={change}
              />
            </label>
            <label>
              Public service description (optional)
              <textarea
                name="description"
                maxLength={360}
                value={draft.description || ""}
                onChange={change}
                rows={3}
              />
            </label>
          </>
        )}
        {type === "barbers" && (
          <label>
            Public bio (optional)
            <textarea
              name="bio"
              maxLength={360}
              value={draft.bio || ""}
              onChange={change}
              rows={3}
            />
          </label>
        )}
        <label className="staff-checkbox">
          <input name="active" type="checkbox" checked={Boolean(draft.active)} onChange={change} />{" "}
          Available for new bookings
        </label>
        {confirm && (
          <p role="alert">
            Existing appointments will not be cancelled or repriced. Review upcoming bookings before
            disabling this item.
          </p>
        )}
        <button className="btn btn-primary">
          {confirm ? "Confirm and save" : item ? "Save changes" : "Add"}
        </button>
      </fieldset>
    </form>
  );
}

function MemberEditor({ member, barbers, busy, save }) {
  const [role, setRole] = useState(member.role === "revoked" ? "manager" : member.role);
  const [barber, setBarber] = useState(member.barber_id || "");
  const [removing, setRemoving] = useState(false);
  return (
    <section className="staff-card">
      <h2>{member.name}</h2>
      <p className="shop-management__email">{member.email}</p>
      {member.role === "owner" || member.is_platform_admin ? (
        <p>Owner · protected access. Ownership transfer is not available here.</p>
      ) : (
        <form
          className="staff-form"
          onSubmit={(event) => {
            event.preventDefault();
            save(`/management/members/${member.id}`, {
              role,
              barber_id: role === "barber" ? Number(barber) : null,
            });
          }}
        >
          <fieldset className="staff-booking-fields" disabled={busy}>
            <label>
              Role
              <select value={role} onChange={(event) => setRole(event.target.value)}>
                <option value="manager">Manager</option>
                <option value="barber">Barber</option>
              </select>
            </label>
            {role === "barber" && (
              <label>
                Assigned barber
                <select required value={barber} onChange={(event) => setBarber(event.target.value)}>
                  <option value="">Choose a barber</option>
                  {barbers
                    .filter((item) => item.active)
                    .map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.name}
                      </option>
                    ))}
                </select>
              </label>
            )}
            <button className="btn btn-primary">
              {member.role === "revoked" ? "Restore staff access" : "Save staff access"}
            </button>
            {member.role === "revoked" && (
              <p>Business access is revoked. Choose a role and save to restore access.</p>
            )}
            {member.role !== "revoked" &&
              (removing ? (
                <div role="alert">
                  <p>
                    Revoke this person's access to this business? Their account and other
                    memberships remain. You can restore access here later.
                  </p>
                  <button
                    type="button"
                    className="btn btn-outline"
                    onClick={() => save(`/management/members/${member.id}`, {}, "delete")}
                  >
                    Confirm revocation
                  </button>
                  <button
                    type="button"
                    className="btn btn-outline"
                    onClick={() => setRemoving(false)}
                  >
                    Keep member
                  </button>
                </div>
              ) : (
                <button type="button" className="btn btn-outline" onClick={() => setRemoving(true)}>
                  Remove business access
                </button>
              ))}
          </fieldset>
        </form>
      )}
    </section>
  );
}

export default function ShopManagement() {
  const { session, setSession } = useAdmin();
  const permissions = session.user.permissions || [];
  const canManageCatalog = permissions.includes("shop.manage");
  const canPublishBranding = permissions.includes("shop.branding.write");
  const canEditHours = permissions.includes("shop.hours.write");
  const canManageMembers = permissions.includes("members.manage");
  const tabs = managementTabs.filter(([, , permission]) => permissions.includes(permission));
  const [params] = useSearchParams();
  const [requestedTab, setTab] = useState(() => params.get("tab"));
  const tab = tabs.find(([value]) => value === requestedTab)?.[0] || tabs[0]?.[0];
  const managementPath = canManageCatalog
    ? "/management"
    : canPublishBranding
      ? "/management/branding"
      : null;
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const [refresh, setRefresh] = useState(0);
  const client = useMemo(
    () =>
      axios.create({
        baseURL: `${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/admin`,
        headers: adminHeaders(session),
        timeout: 20000,
      }),
    [session],
  );
  useEffect(() => {
    if (!managementPath) return;
    const controller = new AbortController();
    client
      .get(managementPath, { signal: controller.signal })
      .then(({ data }) => {
        setData(data);
        setError("");
      })
      .catch((err) => {
        if (!axios.isCancel(err)) {
          if (err.response?.status === 401) setSession(null);
          else setError(formError(err));
        }
      });
    return () => controller.abort();
  }, [client, managementPath, refresh, setSession]);
  const save = async (
    url,
    payload,
    method = "put",
    successMessage = "Business changes saved. Existing booking prices and names are preserved.",
  ) => {
    if (busy) return;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      if (payload instanceof FormData) payload.set("version", data.version);
      const response = await client.request({
        url,
        method,
        data: payload instanceof FormData ? payload : { ...payload, version: data.version },
      });
      setData(response.data);
      setNotice(successMessage);
      return true;
    } catch (err) {
      if (err.response?.status === 401) setSession(null);
      else setError(formError(err));
      return false;
    } finally {
      setBusy(false);
    }
  };
  if (tab === "hours") {
    return <Navigate to="/admin/settings/profile#business-hours" replace />;
  }
  if (!managementPath) return <Navigate to="/admin" replace />;
  const readiness = data && canManageCatalog ? catalogReadiness(data.services, data.barbers) : null;
  const brandingBusy = busy || data?.shop_status !== "active";
  return (
    <main className="staff-page shop-management">
      <header className="staff-page-heading">
        <span className="section-label">{session.user.shop?.name}</span>
        <h1 className="section-title">Business management</h1>
      </header>
      {readiness && !readiness.ready && (
        <section className="staff-card shop-management__setup" aria-labelledby="shop-setup-title">
          <h2 id="shop-setup-title">Get your business ready</h2>
          <p>
            Your business starts without demo services or barbers. Add your real catalog before
            sharing your booking page.
          </p>
          <p>
            {readiness.serviceCount} active services · {readiness.barberCount} active barber
            profiles
          </p>
          <div className="staff-form-actions">
            <button
              type="button"
              className="btn btn-outline"
              disabled={busy}
              onClick={() => setTab("services")}
            >
              Add your services
            </button>
            <button
              type="button"
              className="btn btn-outline"
              disabled={busy}
              onClick={() => setTab("staff")}
            >
              Add barber profiles
            </button>
            <Link className="btn btn-outline" to="/admin/settings/profile">
              Review business details
            </Link>
            {canEditHours && (
              <Link className="btn btn-outline" to="/admin/settings/profile#business-hours">
                Review business hours
              </Link>
            )}
          </div>
          <p>
            Review your business details and each barber’s schedule before taking bookings. New
            businesses start with the default business hours.
          </p>
        </section>
      )}
      {data && (
        <p>
          <Link to={shopPath(session.user.shop.slug)}>Preview your public booking page</Link>
        </p>
      )}
      <nav className="shop-management__tabs" aria-label="Business management sections">
        {tabs.map(([value, label]) => (
          <button
            key={value}
            className={`btn ${tab === value ? "btn-primary" : "btn-outline"}`}
            type="button"
            disabled={busy}
            aria-pressed={tab === value}
            onClick={() => setTab(value)}
          >
            {label}
          </button>
        ))}
      </nav>
      {error && (
        <div className="admin-error" role="alert">
          {error}
          <button
            type="button"
            className="btn btn-outline"
            onClick={() => {
              setData(null);
              setRefresh((value) => value + 1);
            }}
          >
            Refresh business data
          </button>
        </div>
      )}
      {notice && (
        <p className="admin-message" role="status">
          {notice}
        </p>
      )}
      {!data && !error && <p role="status">Loading business management…</p>}
      {data && (
        <div className="shop-management__content">
          {tab === "branding" && (
            <>
              <BrandingGenerator
                key={data.business_name}
                businessName={data.business_name}
                branding={data.branding}
                busy={brandingBusy}
                save={save}
              />
              <p>JPEG, PNG or WebP, up to 2 MB and 3200 pixels wide or tall.</p>
              <div className="shop-management__grid" key={data.version}>
                <BrandingEditor branding={data.branding || {}} busy={brandingBusy} save={save} />
                {[
                  ["logo", "Business logo"],
                  ["cover", "Booking cover photo"],
                ].map(([target, title]) => (
                  <MediaEditor
                    key={target}
                    title={title}
                    target={target}
                    slug={session.user.shop.slug}
                    images={data.branding?.[target] ? [{ image: data.branding[target] }] : []}
                    busy={brandingBusy}
                    save={save}
                    previewClient={data.shop_status !== "active" ? client : undefined}
                  />
                ))}
              </div>
            </>
          )}
          {tab === "portfolios" && (
            <>
              <p>
                Add up to six photos per barber. JPEG, PNG or WebP, up to 2 MB and 3200 pixels wide
                or tall.
              </p>
              {!data.barbers.length && <p>Add a barber in Staff to start a portfolio.</p>}
              <div className="shop-management__grid" key={data.version}>
                {data.barbers.map((barber) => (
                  <MediaEditor
                    key={barber.id}
                    title={barber.name}
                    target="portfolio"
                    barberId={barber.id}
                    slug={session.user.shop.slug}
                    images={barber.portfolio || []}
                    busy={busy}
                    save={save}
                    previewClient={
                      !barber.active || data.shop_status !== "active" ? client : undefined
                    }
                  />
                ))}
              </div>
            </>
          )}
          {tab === "staff" && (
            <>
              {canManageMembers && (
                <>
                  <Link className="btn btn-outline" to="/admin/settings/invite">
                    Invite a staff member
                  </Link>
                  <p>
                    Managers handle bookings, inbox and reports. Barbers view assigned appointments.
                  </p>
                  <div className="shop-management__grid">
                    {data.members.map((member) => (
                      <MemberEditor
                        key={`${data.version}-member-${member.id}`}
                        member={member}
                        barbers={data.barbers}
                        busy={busy}
                        save={save}
                      />
                    ))}
                  </div>
                </>
              )}
              <h2>Barber profiles</h2>
              <p>
                Profiles are bookable staff, not login accounts. Invite a barber account separately
                if they need dashboard access.
              </p>
              <div className="shop-management__grid">
                {data.barbers.map((item) => (
                  <CatalogEditor
                    key={`${data.version}-barber-${item.id}`}
                    item={item}
                    type="barbers"
                    busy={busy}
                    save={save}
                  />
                ))}
                <CatalogEditor
                  key={`${data.version}-new-barber`}
                  type="barbers"
                  busy={busy}
                  save={save}
                />
              </div>
            </>
          )}
          {tab === "services" && (
            <>
              <p>
                Prices and durations are saved on new appointments. Editing a service never reprices
                existing bookings.
              </p>
              <div className="shop-management__grid">
                {data.services.map((item) => (
                  <CatalogEditor
                    key={`${data.version}-service-${item.id}`}
                    item={item}
                    type="services"
                    busy={busy}
                    save={save}
                  />
                ))}
                <CatalogEditor
                  key={`${data.version}-new-service`}
                  type="services"
                  busy={busy}
                  save={save}
                />
              </div>
            </>
          )}
          {tab === "schedules" && (
            <ScheduleEditor key={`${data.version}-barber`} data={data} busy={busy} save={save} />
          )}
        </div>
      )}
    </main>
  );
}
