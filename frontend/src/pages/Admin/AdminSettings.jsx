import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import axios from "axios";
import { CalendarDays, Clock3, Ban, ShieldCheck, UserPlus, ArrowRight } from "lucide-react";
import { useAdmin } from "../../context/AdminContext";

const describeError = (error) => Object.values(error.response?.data?.errors || {}).flat().join(" ") || error.response?.data?.message || "Unable to connect. Please try again.";

export default function AdminSettings({ page = "dashboard" }) {
  const { session, setSession } = useAdmin();
  const [data, setData] = useState(null);
  const [name, setName] = useState(session.user.name);
  const [passwords, setPasswords] = useState({ current_password: "", password: "", password_confirmation: "" });
  const [email, setEmail] = useState("");
  const [sendEmail, setSendEmail] = useState(false);
  const [invitation, setInvitation] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [refresh, setRefresh] = useState(0);
  const client = useMemo(() => axios.create({ baseURL: `${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/admin`, headers: { Authorization: `Bearer ${session.token}`, Accept: "application/json" }, timeout: 20000 }), [session.token]);
  useEffect(() => {
    const controller = new AbortController();
    const endpoint = page === "dashboard" ? "/dashboard" : page === "invite" ? "/invitations" : "/profile";
    client.get(endpoint, { signal: controller.signal }).then(({ data: response }) => {
      setData(response);
      if (page === "profile") setName(response.name);
    }).catch((err) => {
      if (axios.isCancel(err)) return;
      if (err.response?.status === 401) setSession(null);
      else setError(describeError(err));
    });
    return () => controller.abort();
  }, [client, page, refresh, setSession]);
  const submit = async (event) => {
    event.preventDefault(); setBusy(true); setError(""); setMessage(""); setInvitation(null);
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
        const { data: result } = await client.post("/invitations", { email, send_email: sendEmail });
        setInvitation(result); setMessage(result.message); setEmail(""); setRefresh((value) => value + 1);
      }
    } catch (err) {
      if (err.response?.status === 401) setSession(null);
      else setError(describeError(err));
    } finally { setBusy(false); }
  };
  const titles = { dashboard: ["Dashboard", "A clear view of your shop’s schedule. All times are South Africa time."], profile: ["My profile", "Manage the name shown on your administrator account."], security: ["Security", "Keep your administrator account protected."], invite: ["Invite Member", "Invite a trusted colleague to help manage Forge & Fade."] };
  return <main className="staff-page">
    <header className="staff-page-heading"><span className="section-label">Forge & Fade · {page === "dashboard" ? "Overview" : "Settings"}</span><h1 className="section-title">{titles[page][0]}</h1><p>{titles[page][1]}</p></header>
    {error && <div className="admin-error" role="alert">{error}<button className="btn btn-outline" type="button" onClick={() => { setError(""); setRefresh((value) => value + 1); }}>Retry loading</button></div>}
    {message && <p className="admin-message" role="status">{message}</p>}
    {!data && !error && <p role="status">Loading…</p>}
    {page === "dashboard" && data && <>
      <div className="staff-stats">{[["Today’s appointments", data.today_count, CalendarDays], ["Upcoming appointments", data.upcoming_count, Clock3], ["Cancelled · all time", data.cancelled_count, Ban]].map(([label, value, Icon]) => <section className="staff-card" key={label}><Icon size={22} aria-hidden="true" /><p>{label}</p><strong>{value}</strong></section>)}</div>
      <section className="staff-card"><div className="staff-card-heading"><div><h2>Coming up next</h2><p>Your next five confirmed appointments.</p></div><Link className="btn btn-outline" to="/admin/appointments">View appointments <ArrowRight size={16} aria-hidden="true" /></Link></div>{data.next.length ? <div className="staff-upcoming">{data.next.map((booking) => <article key={booking.id}><div><h3>{booking.customer_name}</h3><p>{booking.service_name} · {booking.barber_name}</p></div><p>{booking.booking_date.slice(0, 10)} · {booking.booking_time.slice(0, 5)}</p></article>)}</div> : <p>No upcoming appointments yet. New bookings will appear here.</p>}</section>
    </>}
    {page !== "dashboard" && data && <div className="staff-settings-grid"><section className="staff-card">
      <h2>{page === "profile" ? "Personal information" : page === "security" ? "Change password" : "Invite an administrator"}</h2>
      <p>{page === "profile" ? "Your account details for Forge & Fade." : page === "security" ? "Changing your password signs out all sessions, including this one." : "Members receive full administrator access, including appointments and invitations. Only invite people you trust."}</p>
      <form className="staff-form" onSubmit={submit}>
        {page === "profile" && <><label>Full name<input autoComplete="name" maxLength={100} required value={name} onChange={(event) => setName(event.target.value)} /></label><label>Email address<input type="email" value={data.email} readOnly /></label><small>Your sign-in email is read-only to protect your account.</small></>}
        {page === "security" && <>{[["current_password", "Current password", "current-password"], ["password", "New password", "new-password"], ["password_confirmation", "Confirm new password", "new-password"]].map(([field, label, autocomplete]) => <label key={field}>{label}<input type="password" autoComplete={autocomplete} required minLength={field === "current_password" ? 1 : 12} maxLength={200} value={passwords[field]} onChange={(event) => setPasswords({ ...passwords, [field]: event.target.value })} /></label>)}<small>Use at least 12 characters. Choose a password you do not use elsewhere.</small></>}
        {page === "invite" && <><label>Member’s email<input type="email" autoComplete="off" maxLength={150} required value={email} onChange={(event) => setEmail(event.target.value)} /></label><label className="staff-checkbox"><input type="checkbox" checked={sendEmail} disabled={!data.email_ready} onChange={(event) => setSendEmail(event.target.checked)} />Send invitation by email</label>{!data.email_ready && <p className="staff-notice">Email delivery is not connected yet. You can still create a private invitation link and send it yourself.</p>}</>}
        <div className="staff-form-actions"><button className="btn btn-primary" disabled={busy || (page === "profile" && name === data.name)}>{busy ? "Please wait…" : page === "profile" ? "Save changes" : page === "security" ? "Change password" : sendEmail ? "Send invitation" : "Create invitation link"}</button></div>
      </form>
      {invitation && <div className="staff-invitation" role="region" aria-label="Private invitation"><label>Private invitation link<textarea readOnly value={invitation.link} onFocus={(event) => event.target.select()} /></label><p>Select and copy this link. It works once, only for the invited email, and expires after 48 hours.</p></div>}
    </section><aside className="staff-card staff-summary">{page === "invite" ? <UserPlus aria-hidden="true" /> : <ShieldCheck aria-hidden="true" />}<h2>{page === "profile" ? "Your account" : page === "security" ? "Account protection" : "Invite safely"}</h2><p>{page === "profile" ? session.user.name : page === "security" ? "Your password is securely hashed. It is never displayed or emailed." : "An invitation grants real access to your shop’s administration. Keep the link private."}</p>{page === "profile" && <><p className="staff-break">{data.email}</p><p>Administrator</p><Link to="/admin/settings/security">Manage security →</Link></>}{page === "security" && <p>Sign-in sessions expire after eight hours. Sign out when using a shared computer.</p>}{page === "invite" && <p>This is for Forge & Fade staff—not customers or workspaces.</p>}</aside></div>}
    {page === "invite" && data && <section className="staff-card staff-invitations"><h2>Recent invitations</h2><p>Latest 50 invitations. Used and expired links cannot register another account.</p>{data.invitations.length ? <ul>{data.invitations.map((item) => <li key={item.id}><strong>{item.email}</strong><span className="admin-badge">{item.used_at ? "Accepted" : item.expires_at.replace(" ", "T") <= data.now.slice(0, 19) ? "Expired" : "Pending"}</span><small>Expires {item.expires_at} · South Africa time</small></li>)}</ul> : <p>No invitations yet.</p>}</section>}
  </main>;
}
