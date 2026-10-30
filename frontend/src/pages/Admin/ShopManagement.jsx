import { useEffect, useMemo, useState } from "react";
import { Link, Navigate } from "react-router-dom";
import axios from "axios";
import { adminHeaders } from "../../utils/adminHeaders";
import { useAdmin } from "../../context/AdminContext";
import { formError } from "../../utils/formError";
import { catalogReadiness } from "../../utils/shopOnboarding";
import { shopPath } from "../../utils/shopLinks";
import "./ShopManagement.scss";

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
          </>
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
              <p>Shop access is revoked. Choose a role and save to restore access.</p>
            )}
            {member.role !== "revoked" &&
              (removing ? (
                <div role="alert">
                  <p>
                    Revoke this person's access to this shop? Their account and other memberships
                    remain. You can restore access here later.
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
                  Remove shop access
                </button>
              ))}
          </fieldset>
        </form>
      )}
    </section>
  );
}

function ScheduleEditor({ data, busy, save }) {
  const [barber, setBarber] = useState(String(data.barbers[0]?.id || ""));
  const [draft, setDraft] = useState(data.schedules[barber]);
  const [breaks, setBreaks] = useState(
    (draft?.breaks || []).map((range) => range.join("-")).join(", "),
  );
  const [closed, setClosed] = useState((draft?.closed_dates || []).join(", "));
  const choose = (id) => {
    const next = data.schedules[id];
    setBarber(id);
    setDraft(next);
    setBreaks((next.breaks || []).map((range) => range.join("-")).join(", "));
    setClosed((next.closed_dates || []).join(", "));
  };
  if (!draft) return <p>Add a barber profile first.</p>;
  const updateDay = (day, value) => setDraft({ ...draft, hours: { ...draft.hours, [day]: value } });
  return (
    <form
      className="staff-card staff-form"
      onSubmit={(event) => {
        event.preventDefault();
        save(`/management/barbers/${barber}/schedule`, {
          hours: draft.hours,
          breaks: breaks.trim()
            ? breaks.split(",").map((range) =>
                range
                  .trim()
                  .split("-")
                  .map((time) => time.trim()),
              )
            : [],
          closed_dates: closed.trim() ? closed.split(",").map((date) => date.trim()) : [],
        });
      }}
    >
      <h2>Weekly availability</h2>
      <p>
        Barber hours are limited by the shop's business hours and breaks. Existing future
        appointments must remain valid.
      </p>
      <fieldset className="staff-booking-fields" disabled={busy}>
        <label>
          Barber profile
          <select value={barber} onChange={(event) => choose(event.target.value)}>
            {data.barbers.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
                {!item.active ? " (inactive)" : ""}
              </option>
            ))}
          </select>
        </label>
        {["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"].map(
          (name, index) => {
            const day = index + 1;
            const range = draft.hours[day];
            return (
              <div className="shop-management__day" key={day}>
                <label className="staff-checkbox">
                  <input
                    type="checkbox"
                    checked={Boolean(range)}
                    onChange={(event) =>
                      updateDay(day, event.target.checked ? ["08:00", "18:00"] : null)
                    }
                  />
                  {name}
                </label>
                {range && (
                  <>
                    <label>
                      {name} start
                      <input
                        type="time"
                        required
                        value={range[0]}
                        onChange={(event) => updateDay(day, [event.target.value, range[1]])}
                      />
                    </label>
                    <label>
                      {name} end
                      <input
                        type="time"
                        required
                        value={range[1]}
                        onChange={(event) => updateDay(day, [range[0], event.target.value])}
                      />
                    </label>
                  </>
                )}
              </div>
            );
          },
        )}
        <label>
          Additional daily breaks
          <input
            value={breaks}
            onChange={(event) => setBreaks(event.target.value)}
            placeholder="13:00-14:00, 16:00-16:15"
            maxLength={150}
          />
          <small>Separate ranges with commas. Shop-wide breaks still apply.</small>
        </label>
        <label>
          Days off
          <input
            value={closed}
            onChange={(event) => setClosed(event.target.value)}
            placeholder="2026-12-25, 2027-01-01"
            maxLength={4500}
          />
          <small>Use YYYY-MM-DD dates separated by commas.</small>
        </label>
        <button className="btn btn-primary">Save barber schedule</button>
      </fieldset>
    </form>
  );
}

export default function ShopManagement() {
  const { session, setSession } = useAdmin();
  const allowed = session.user.permissions?.includes("shop.manage");
  const [tab, setTab] = useState("staff");
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
    if (!allowed) return;
    const controller = new AbortController();
    client
      .get("/management", { signal: controller.signal })
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
  }, [allowed, client, refresh, setSession]);
  const save = async (url, payload, method = "put") => {
    if (busy) return;
    setBusy(true);
    setError("");
    setNotice("");
    try {
      const response = await client.request({
        url,
        method,
        data: { ...payload, version: data.version },
      });
      setData(response.data);
      setNotice("Shop changes saved. Existing booking prices and names are preserved.");
    } catch (err) {
      if (err.response?.status === 401) setSession(null);
      else setError(formError(err));
    } finally {
      setBusy(false);
    }
  };
  if (!allowed) return <Navigate to="/admin" replace />;
  const readiness = data ? catalogReadiness(data.services, data.barbers) : null;
  return (
    <main className="staff-page shop-management">
      <header className="staff-page-heading">
        <span className="section-label">{session.user.shop?.name} · Owner tools</span>
        <h1 className="section-title">Shop management</h1>
        <p>
          Manage staff access, barber profiles, services and schedules. Changes affect only this
          shop.
        </p>
      </header>
      {readiness && !readiness.ready && (
        <section className="staff-card shop-management__setup" aria-labelledby="shop-setup-title">
          <h2 id="shop-setup-title">Get your shop ready</h2>
          <p>
            Your shop starts without demo services or barbers. Add your real catalog before sharing
            your booking page.
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
              Review shop details
            </Link>
          </div>
          <p>
            Review your shop details and each barber’s schedule before taking bookings. New shops
            start with the default business hours.
          </p>
        </section>
      )}
      {data && (
        <p>
          <Link to={shopPath(session.user.shop.slug)}>Preview your public booking page</Link>
        </p>
      )}
      <nav className="shop-management__tabs" aria-label="Shop management sections">
        {[
          ["staff", "Staff"],
          ["services", "Services"],
          ["schedules", "Barber schedules"],
        ].map(([value, label]) => (
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
            Refresh shop data
          </button>
        </div>
      )}
      {notice && (
        <p className="admin-message" role="status">
          {notice}
        </p>
      )}
      {!data && !error && <p role="status">Loading shop management…</p>}
      {data && (
        <div className="shop-management__content">
          {tab === "staff" && (
            <>
              <Link className="btn btn-outline" to="/admin/settings/invite">
                Invite a staff member
              </Link>
              <p>
                Manager: bookings, inbox and reports. Barber: assigned appointments only. Owners
                cannot be changed here.
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
          {tab === "schedules" && <ScheduleEditor data={data} busy={busy} save={save} />}
        </div>
      )}
    </main>
  );
}
