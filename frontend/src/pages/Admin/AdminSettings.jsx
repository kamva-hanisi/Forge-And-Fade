import { useEffect, useMemo, useState } from "react";
import { Link, Navigate } from "react-router-dom";
import axios from "axios";
import { CalendarDays, Clock3, Ban, ShieldCheck, UserPlus, ArrowRight } from "lucide-react";
import { useAdmin } from "../../context/AdminContext";
import ShopProfile from "./ShopProfile";

const describeError = (error) =>
  Object.values(error.response?.data?.errors || {})
    .flat()
    .join(" ") ||
  error.response?.data?.message ||
  "Unable to connect. Please try again.";

export default function AdminSettings({ page = "dashboard" }) {
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
        headers: { Authorization: `Bearer ${session.token}`, Accept: "application/json" },
        timeout: 20000,
      }),
    [session.token],
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
    dashboard: [
      "Dashboard",
      "A clear view of your shop’s schedule. All times are South Africa time.",
    ],
    profile: ["My profile", "Manage the name shown on your administrator account."],
    security: ["Security", "Keep your administrator account protected."],
    invite: [
      "Invite Member",
      `Invite a trusted colleague to help manage ${session.user.shop?.name || "your shop"}.`,
    ],
  };
  if (page === "invite" && !canInvite) return <Navigate to="/admin" replace />;
  return (
    <main className="staff-page">
      <header className="staff-page-heading">
        <span className="section-label">
          {session.user.shop?.name || "Forge & Fade"} ·{" "}
          {page === "dashboard" ? "Overview" : "Settings"}
        </span>
        <h1 className="section-title">{titles[page][0]}</h1>
        <p>{titles[page][1]}</p>
      </header>
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
          <div className="staff-stats">
            {[
              ["Today’s appointments", data.today_count, CalendarDays],
              ["Upcoming appointments", data.upcoming_count, Clock3],
              ["Cancelled · all time", data.cancelled_count, Ban],
            ].map(([label, value, Icon]) => (
              <section className="staff-card" key={label}>
                <Icon size={22} aria-hidden="true" />
                <p>{label}</p>
                <strong>{value}</strong>
              </section>
            ))}
          </div>
          {data.statistics && (
            <section className="staff-card staff-report">
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
                      setData(null);
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
            </section>
          )}
          <section className="staff-card">
            <div className="staff-card-heading">
              <div>
                <h2>Coming up next</h2>
                <p>Your next five confirmed appointments.</p>
              </div>
              <Link className="btn btn-outline" to="/admin/appointments">
                View appointments <ArrowRight size={16} aria-hidden="true" />
              </Link>
            </div>
            {data.next.length ? (
              <div className="staff-upcoming">
                {data.next.map((booking) => (
                  <article key={booking.id}>
                    <div>
                      <h3>{booking.customer_name}</h3>
                      <p>
                        {booking.service_name} · {booking.barber_name}
                      </p>
                    </div>
                    <p>
                      {booking.booking_date.slice(0, 10)} · {booking.booking_time.slice(0, 5)}
                    </p>
                  </article>
                ))}
              </div>
            ) : (
              <p>No upcoming appointments yet. New bookings will appear here.</p>
            )}
          </section>
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
                  : "Choose the access this person needs. Only the shop owner can send invitations."}
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
                  : "An invitation grants real access to your shop’s administration. Keep the link private."}
            </p>
            {page === "profile" && (
              <>
                <p className="staff-break">{data.email}</p>
                <p>
                  {session.user.role?.replace("_", " ") || "Staff"} · {session.user.shop?.name}
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
