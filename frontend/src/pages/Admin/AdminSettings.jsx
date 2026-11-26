import { useEffect, useMemo, useState } from "react";
import { Link, Navigate } from "react-router-dom";
import axios from "axios";
import { adminHeaders } from "../../utils/adminHeaders";
import { accessRoleLabel } from "../../utils/adminDashboard";
import {
  ArrowRight,
  CalendarDays,
  Clock3,
  Inbox,
  MailWarning,
  Plus,
  Scissors,
  Settings2,
  ShieldCheck,
  UserPlus,
} from "lucide-react";
import { useAdmin } from "../../context/AdminContext";
import ShopProfile from "./ShopProfile";
import "./AdminDashboard.scss";

const todayLink = "/admin/appointments?view=today&status=confirmed";
const dashboardDate = new Intl.DateTimeFormat("en-ZA", {
  dateStyle: "full",
  timeZone: "UTC",
});

function DashboardOverview({ title, shopName, data }) {
  return (
    <div className="staff-dashboard__overview">
      <header className="staff-dashboard__hero">
        <div>
          <p className="staff-dashboard__shop">{shopName || "Forge & Fade"}</p>
          <h1>{title}</h1>
          {data && (
            <p className="staff-dashboard__date">
              <CalendarDays size={16} aria-hidden="true" />
              <span>
                <time dateTime={data.today}>{dashboardDate.format(new Date(data.today))}</time>
                <small>
                  {data.timezone === "Africa/Johannesburg" ? "South Africa time" : data.timezone}
                </small>
              </span>
            </p>
          )}
        </div>
        <div className="staff-dashboard__photos" aria-hidden="true">
          <div className="staff-dashboard__photo-shop" />
          <div className="staff-dashboard__photo-tools" />
        </div>
      </header>
      {data && (
        <div className="staff-dashboard__metrics" aria-label="Business overview" role="group">
          {[
            ["Today’s appointments", data.today_count, CalendarDays, todayLink, "View schedule"],
            [
              "New contact messages",
              data.new_message_count,
              Inbox,
              "/admin/inbox?status=new",
              "Open inbox",
            ],
            [
              "Failed booking emails",
              data.failed_email_count,
              MailWarning,
              "/admin/appointments?email_status=failed",
              "Review delivery",
            ],
          ]
            .filter(([, value]) => value != null)
            .map(([label, value, Icon, to, action]) => (
              <Link
                className="staff-dashboard__metric"
                data-attention={Icon === MailWarning && value > 0}
                key={label}
                to={to}
              >
                <span className="staff-dashboard__icon">
                  <Icon size={20} aria-hidden="true" />
                </span>
                <span className="staff-dashboard__metric-label">{label}</span>
                <strong>{value}</strong>
                <span className="staff-dashboard__action">
                  {action} <ArrowRight size={16} aria-hidden="true" />
                </span>
              </Link>
            ))}
        </div>
      )}
    </div>
  );
}

function DashboardOwnerTools({ permissions }) {
  const links = [
    ["Business settings", "/admin/settings/profile", Settings2, "shop.write"],
    ["Services", "/admin/management?tab=services", Scissors, "shop.manage"],
    ["Business hours", "/admin/management?tab=hours", Clock3, "shop.manage"],
    ["Invite staff", "/admin/settings/invite", UserPlus, "members.invite"],
  ].filter(([, , , permission]) => permissions?.includes(permission));

  if (!links.length) return null;

  return (
    <section
      className="staff-card staff-dashboard__panel staff-dashboard__owner-tools"
      aria-labelledby="dashboard-owner-tools"
    >
      <div className="staff-card-heading">
        <h2 id="dashboard-owner-tools">Owner tools</h2>
      </div>
      <div className="staff-dashboard__tools">
        {links.map(([label, to, Icon]) => (
          <Link key={label} to={to}>
            <span className="staff-dashboard__tool-icon">
              <Icon size={18} aria-hidden="true" />
            </span>
            <span>{label}</span>
            <ArrowRight size={16} aria-hidden="true" />
          </Link>
        ))}
      </div>
    </section>
  );
}

const describeError = (error) =>
  Object.values(error.response?.data?.errors || {})
    .flat()
    .join(" ") ||
  error.response?.data?.message ||
  "Unable to connect. Please try again.";

export default function AdminSettings({ page = "dashboard", dashboard }) {
  const { session, setSession } = useAdmin();
  const [data, setData] = useState(null);
  const [name, setName] = useState(session.user.name);
  const [passwords, setPasswords] = useState({
    current_password: "",
    password: "",
    password_confirmation: "",
  });
  const [email, setEmail] = useState("");
  const [sendEmail, setSendEmail] = useState(false);
  const [inviteRole, setInviteRole] = useState("manager");
  const [inviteBarber, setInviteBarber] = useState("");
  const canInvite = session.user.permissions?.includes("members.invite");
  const [invitation, setInvitation] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [refresh, setRefresh] = useState(0);
  const [days, setDays] = useState(30);
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
    if (page === "invite" && !canInvite) return;
    const controller = new AbortController();
    const endpoint =
      page === "dashboard" ? "/dashboard" : page === "invite" ? "/invitations" : "/profile";
    client
      .get(endpoint, {
        signal: controller.signal,
        params: page === "dashboard" ? { days } : undefined,
      })
      .then(({ data: response }) => {
        setData(response);
        if (page === "profile") setName(response.name);
      })
      .catch((err) => {
        if (axios.isCancel(err)) return;
        if (err.response?.status === 401) setSession(null);
        else setError(describeError(err));
      });
    return () => controller.abort();
  }, [client, page, refresh, setSession, canInvite, days]);
  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    setMessage("");
    setInvitation(null);
    try {
      if (page === "profile") {
        const { data: profile } = await client.patch("/profile", { name });
        setData(profile);
        setSession((previous) => ({ ...previous, user: { ...previous.user, ...profile } }));
        setMessage("Your profile has been saved.");
      } else if (page === "security") {
        await client.put("/password", passwords);
        setPasswords({ current_password: "", password: "", password_confirmation: "" });
        setSession(null);
      } else {
        const { data: result } = await client.post("/invitations", {
          email,
          send_email: sendEmail,
          role: inviteRole,
          ...(inviteRole === "barber" ? { barber_id: Number(inviteBarber) } : {}),
        });
        setInvitation(result);
        setMessage(result.message);
        setEmail("");
        setRefresh((value) => value + 1);
      }
    } catch (err) {
      if (err.response?.status === 401) setSession(null);
      else setError(describeError(err));
    } finally {
      setBusy(false);
    }
  };
  const titles = {
    profile: ["My profile", "Manage the name shown on your administrator account."],
    security: ["Security", "Keep your administrator account protected."],
    invite: [
      "Invite Member",
      `Invite a trusted colleague to help manage ${session.user.shop?.name || "your business"}.`,
    ],
  };
  if (page === "invite" && !canInvite) return <Navigate to="/admin" replace />;
  return (
    <main className={page === "dashboard" ? "staff-page staff-dashboard" : "staff-page"}>
      {page === "dashboard" ? (
        <DashboardOverview title={dashboard.title} shopName={session.user.shop?.name} data={data} />
      ) : (
        <header className="staff-page-heading">
          <span className="section-label">
            {session.user.shop?.name || "Forge & Fade"} · Settings
          </span>
          <h1 className="section-title">{titles[page][0]}</h1>
          <p>{titles[page][1]}</p>
        </header>
      )}
      {error && (
        <div className="admin-error" role="alert">
          {error}
          <button
            className="btn btn-outline"
            type="button"
            onClick={() => {
              setError("");
              setRefresh((value) => value + 1);
            }}
          >
            Retry loading
          </button>
        </div>
      )}
      {message && (
        <p className="admin-message" role="status">
          {message}
        </p>
      )}
      {!data && !error && <p role="status">Loading…</p>}
      {page === "dashboard" && data && (
        <>
          {data.shop_status === "suspended" && (
            <p className="admin-error" role="status">
              This business is suspended. New bookings and operational changes are paused. Existing
              appointments remain available to view or cancel. Contact the platform administrator
              for help.
            </p>
          )}
          {dashboard.kind === "owner" && (
            <DashboardOwnerTools permissions={session.user.permissions} />
          )}
          <div className="staff-dashboard__schedule">
            <section className="staff-card staff-dashboard__panel" aria-labelledby="today-schedule">
              <div className="staff-card-heading">
                <div>
                  <h2 id="today-schedule">Today’s schedule</h2>
                  <p>
                    {session.user.role === "barber"
                      ? "Confirmed appointments assigned to you"
                      : "Confirmed appointments in start-time order"}
                  </p>
                </div>
                <div className="staff-dashboard__schedule-actions">
                  <Link className="staff-dashboard__action" to={todayLink}>
                    View day <ArrowRight size={16} aria-hidden="true" />
                  </Link>
                  {dashboard.kind === "staff" &&
                    data.shop_status === "active" &&
                    session.user.permissions?.includes("bookings.write") && (
                      <Link className="staff-dashboard__create" to="/admin/appointments/new">
                        <Plus size={16} aria-hidden="true" /> New appointment
                      </Link>
                    )}
                </div>
              </div>
              {data.today_appointments?.length ? (
                <>
                  <ul className="staff-dashboard__appointments">
                    {data.today_appointments.map((booking) => (
                      <li key={booking.id}>
                        <time dateTime={booking.booking_time.slice(0, 5)}>
                          {booking.booking_time.slice(0, 5)}
                        </time>
                        <div>
                          <strong>{booking.customer_name}</strong>
                          <p>
                            {booking.service_name} · {booking.barber_name}
                          </p>
                        </div>
                      </li>
                    ))}
                  </ul>
                  {data.today_count > data.today_appointments.length && (
                    <p className="staff-dashboard__more">
                      Showing {data.today_appointments.length} of {data.today_count}
                    </p>
                  )}
                </>
              ) : (
                <div className="staff-dashboard__empty">
                  <CalendarDays size={24} aria-hidden="true" />
                  <p>No confirmed appointments scheduled for today.</p>
                </div>
              )}
            </section>
            <section className="staff-card staff-dashboard__panel" aria-labelledby="coming-up">
              <div className="staff-card-heading">
                <div>
                  <h2 id="coming-up">Coming up next</h2>
                  <p>{data.upcoming_count} upcoming confirmed appointments</p>
                </div>
                <Clock3 size={20} aria-hidden="true" />
              </div>
              {data.next.length ? (
                <ul className="staff-dashboard__next">
                  {data.next.map((booking) => (
                    <li key={booking.id}>
                      <strong>{booking.customer_name}</strong>
                      <p>
                        {booking.service_name} · {booking.barber_name}
                      </p>
                      <p>
                        {booking.booking_date.slice(0, 10)} · {booking.booking_time.slice(0, 5)}
                      </p>
                    </li>
                  ))}
                </ul>
              ) : (
                <p>No upcoming appointments.</p>
              )}
            </section>
          </div>
          {data.statistics && (
            <section className="staff-card staff-report staff-dashboard__panel">
              <div className="staff-card-heading">
                <div>
                  <h2>Booking activity</h2>
                  <p>
                    Appointments scheduled from {data.statistics.from} to {data.statistics.to},
                    inclusive.
                  </p>
                </div>
                <label htmlFor="report-days">
                  Reporting period
                  <select
                    id="report-days"
                    value={days}
                    onChange={(event) => {
                      setError("");
                      setDays(Number(event.target.value));
                    }}
                  >
                    <option value={7}>Last 7 days</option>
                    <option value={30}>Last 30 days</option>
                    <option value={90}>Last 90 days</option>
                  </select>
                </label>
              </div>
              <div className="staff-report__counts">
                <p>
                  <strong>{data.statistics.confirmed_count}</strong> confirmed in period
                </p>
                <p>
                  <strong>{data.statistics.cancelled_count}</strong> cancelled in period
                </p>
                <p>
                  <strong>{data.past_count}</strong> earlier confirmed appointments · all time
                </p>
                <p>
                  <strong>{data.cancelled_count}</strong> cancelled appointments · all time
                </p>
              </div>
              {data.statistics.booked_values !== null && (
                <div className="staff-report__value">
                  <h3>Confirmed booked value</h3>
                  {data.statistics.booked_values.length ? (
                    data.statistics.booked_values.map((value) => (
                      <p key={value.currency}>
                        <strong>
                          {new Intl.NumberFormat("en-ZA", {
                            style: "currency",
                            currency: value.currency,
                          }).format(Number(value.amount))}
                        </strong>{" "}
                        · {value.booking_count} priced{" "}
                        {value.booking_count === 1 ? "appointment" : "appointments"}
                      </p>
                    ))
                  ) : (
                    <p>No priced confirmed appointments in this period.</p>
                  )}
                  <p>
                    Saved booking prices only; cancelled appointments are excluded. This is not
                    collected revenue or proof of completed services.
                  </p>
                  {data.statistics.unpriced_count > 0 && (
                    <p role="status">
                      {data.statistics.unpriced_count} confirmed{" "}
                      {data.statistics.unpriced_count === 1
                        ? "appointment has"
                        : "appointments have"}{" "}
                      unknown historical pricing and{" "}
                      {data.statistics.unpriced_count === 1 ? "is" : "are"} excluded from these
                      amounts.
                    </p>
                  )}
                </div>
              )}
              {data.statistics.recorded_receipts !== null && (
                <div className="staff-report__value">
                  <h3>Recorded payment receipts</h3>
                  {data.statistics.recorded_receipts?.length ? (
                    data.statistics.recorded_receipts.map((value) => (
                      <p key={value.currency}>
                        <strong>
                          {new Intl.NumberFormat("en-ZA", {
                            style: "currency",
                            currency: value.currency,
                          }).format(Number(value.amount))}
                        </strong>{" "}
                        · {value.receipt_count} recorded{" "}
                        {Number(value.receipt_count) === 1 ? "receipt" : "receipts"}
                      </p>
                    ))
                  ) : (
                    <p>No received-payment records for this period.</p>
                  )}
                  <p>
                    Grouped by the received date declared by staff. Voided records are excluded.
                    These are manual receipt records, not bank-verified transactions; this website
                    does not charge or refund customers.
                  </p>
                </div>
              )}
            </section>
          )}
        </>
      )}
      {page !== "dashboard" && data && (
        <div className="staff-settings-grid">
          <section className="staff-card">
            <h2>
              {page === "profile"
                ? "Personal information"
                : page === "security"
                  ? "Change password"
                  : "Invite an administrator"}
            </h2>
            <p>
              {page === "profile"
                ? "Your account details for Forge & Fade."
                : page === "security"
                  ? "Changing your password signs out all sessions, including this one."
                  : "Choose the access this person needs. Only the business owner can send invitations."}
            </p>
            <form className="staff-form" onSubmit={submit}>
              {page === "profile" && (
                <>
                  <label>
                    Full name
                    <input
                      autoComplete="name"
                      maxLength={100}
                      required
                      value={name}
                      onChange={(event) => setName(event.target.value)}
                    />
                  </label>
                  <label>
                    Email address
                    <input type="email" value={data.email} readOnly />
                  </label>
                  <small>Your sign-in email is read-only to protect your account.</small>
                </>
              )}
              {page === "security" && (
                <>
                  {[
                    ["current_password", "Current password", "current-password"],
                    ["password", "New password", "new-password"],
                    ["password_confirmation", "Confirm new password", "new-password"],
                  ].map(([field, label, autocomplete]) => (
                    <label key={field}>
                      {label}
                      <input
                        type="password"
                        autoComplete={autocomplete}
                        required
                        minLength={field === "current_password" ? 1 : 12}
                        maxLength={200}
                        value={passwords[field]}
                        onChange={(event) =>
                          setPasswords({ ...passwords, [field]: event.target.value })
                        }
                      />
                    </label>
                  ))}
                  <small>
                    Use at least 12 characters. Choose a password you do not use elsewhere.
                  </small>
                </>
              )}
              {page === "invite" && (
                <>
                  <label>
                    Member role
                    <select
                      value={inviteRole}
                      onChange={(event) => {
                        setInviteRole(event.target.value);
                        setInviteBarber("");
                      }}
                    >
                      <option value="manager">Manager — manage appointments</option>
                      <option value="barber">Barber — view assigned schedule only</option>
                    </select>
                  </label>
                  {inviteRole === "barber" && (
                    <label>
                      Assigned barber
                      <select
                        required
                        value={inviteBarber}
                        onChange={(event) => setInviteBarber(event.target.value)}
                      >
                        <option value="">Choose a barber</option>
                        {data.barbers.map((barber) => (
                          <option key={barber.id} value={barber.id}>
                            {barber.name}
                          </option>
                        ))}
                      </select>
                    </label>
                  )}
                  <label>
                    Member’s email
                    <input
                      type="email"
                      autoComplete="off"
                      maxLength={150}
                      required
                      value={email}
                      onChange={(event) => setEmail(event.target.value)}
                    />
                  </label>
                  <label className="staff-checkbox">
                    <input
                      type="checkbox"
                      checked={sendEmail}
                      disabled={!data.email_ready}
                      onChange={(event) => setSendEmail(event.target.checked)}
                    />
                    Send invitation by email
                  </label>
                  {!data.email_ready && (
                    <p className="staff-notice">
                      Email delivery is not connected yet. You can still create a private invitation
                      link and send it yourself.
                    </p>
                  )}
                </>
              )}
              <div className="staff-form-actions">
                <button
                  className="btn btn-primary"
                  disabled={busy || (page === "profile" && name === data.name)}
                >
                  {busy
                    ? "Please wait…"
                    : page === "profile"
                      ? "Save changes"
                      : page === "security"
                        ? "Change password"
                        : sendEmail
                          ? "Send invitation"
                          : "Create invitation link"}
                </button>
              </div>
            </form>
            {invitation && (
              <div className="staff-invitation" role="region" aria-label="Private invitation">
                <label>
                  Private invitation link
                  <textarea
                    readOnly
                    value={invitation.link}
                    onFocus={(event) => event.target.select()}
                  />
                </label>
                <p>
                  Select and copy this link. It works once, only for the invited email, and expires
                  after 48 hours.
                </p>
              </div>
            )}
          </section>
          <aside className="staff-card staff-summary">
            {page === "invite" ? (
              <UserPlus aria-hidden="true" />
            ) : (
              <ShieldCheck aria-hidden="true" />
            )}
            <h2>
              {page === "profile"
                ? "Your account"
                : page === "security"
                  ? "Account protection"
                  : "Invite safely"}
            </h2>
            <p>
              {page === "profile"
                ? session.user.name
                : page === "security"
                  ? "Your password is securely hashed. It is never displayed or emailed."
                  : "An invitation grants real access to your business’s administration. Keep the link private."}
            </p>
            {page === "profile" && (
              <>
                <p className="staff-break">{data.email}</p>
                <p>
                  {accessRoleLabel(
                    session.user.website_role ||
                      (session.user.role === "owner" ? "shop_owner" : session.user.role),
                  )}
                  {session.user.shop && ` · ${session.user.shop.name}`}
                </p>
                <Link to="/admin/settings/security">Manage security →</Link>
              </>
            )}
            {page === "security" && (
              <p>
                Sign-in sessions expire after eight hours. Sign out when using a shared computer.
              </p>
            )}
            {page === "invite" && (
              <p>This is for Forge & Fade staff—not customers or workspaces.</p>
            )}
          </aside>
        </div>
      )}
      {page === "profile" && session.user.permissions?.includes("shop.write") && (
        <ShopProfile client={client} />
      )}
      {page === "invite" && data && (
        <section className="staff-card staff-invitations">
          <h2>Recent invitations</h2>
          <p>Latest 50 invitations. Used and expired links cannot register another account.</p>
          {data.invitations.length ? (
            <ul>
              {data.invitations.map((item) => (
                <li key={item.id}>
                  <strong>{item.email}</strong>
                  <span className="admin-badge">
                    {item.used_at
                      ? "Accepted"
                      : item.expires_at.replace(" ", "T") <= data.now.slice(0, 19)
                        ? "Expired"
                        : "Pending"}
                  </span>
                  <small>Expires {item.expires_at} · South Africa time</small>
                </li>
              ))}
            </ul>
          ) : (
            <p>No invitations yet.</p>
          )}
        </section>
      )}
    </main>
  );
}
