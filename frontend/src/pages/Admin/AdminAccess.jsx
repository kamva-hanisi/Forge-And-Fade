import { useEffect, useState } from "react";
import { Link, Navigate, useNavigate, useSearchParams } from "react-router-dom";
import { ArrowRight, Eye, EyeOff, ShieldCheck } from "lucide-react";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import "./AdminAccess.scss";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

export default function AdminAccess({ signup = false }) {
  const { session, authenticate, busy } = useAdmin();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [fields, setFields] = useState({ name: "", email: signup ? params.get("email") || "" : "", password: "", password_confirmation: "", invitation: signup ? params.get("invitation") || "" : "" });
  const [requiresInvitation, setRequiresInvitation] = useState(true);
  const [checking, setChecking] = useState(signup);
  const [ready, setReady] = useState(!signup);
  const [refresh, setRefresh] = useState(0);
  const [visible, setVisible] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!signup) return;
    const controller = new AbortController();
    const check = async () => {
      setChecking(true); setReady(false); setError("");
      try {
        const { data } = await axios.get(`${apiUrl}/admin/registration`, { signal: controller.signal, timeout: 20000 });
        setRequiresInvitation(data.requires_invitation); setReady(true);
      } catch (err) { if (!axios.isCancel(err)) setError("We could not check admin registration. Please retry."); }
      finally { if (!controller.signal.aborted) setChecking(false); }
    };
    check();
    return () => controller.abort();
  }, [signup, refresh]);

  if (session) return <Navigate to="/admin" replace />;

  const change = (event) => { setFields((previous) => ({ ...previous, [event.target.name]: event.target.value })); setError(""); };
  const submit = async (event) => {
    event.preventDefault(); setError("");
    if (signup && fields.password !== fields.password_confirmation) { setError("The passwords do not match."); return; }
    try {
      await authenticate(signup ? "register" : "login", signup ? fields : { email: fields.email, password: fields.password });
      navigate("/admin", { replace: true });
    } catch (err) {
      const errors = err.response?.data?.errors;
      if (errors?.invitation) setRequiresInvitation(true);
      setError(err.response?.status === 429 ? "Too many attempts. Wait a minute and try again." : Object.values(errors || {}).flat().join(" ") || err.response?.data?.message || "Unable to connect. Please try again.");
    }
  };

  return <main className="admin-access container">
    <div className="admin-access__intro"><span className="section-label">Forge & Fade · Administration</span><h1 className="section-title">Your shop.<br />Your schedule.</h1><p>One place to manage appointments, look after your team, and keep the day running smoothly.</p><span className="admin-access__staff"><ShieldCheck size={18} aria-hidden="true" /> For shop owners and authorized staff</span></div>
    <section className="admin-access__card" aria-labelledby="admin-access-title">
      <nav aria-label="Admin account access"><Link to="/admin/signin" aria-current={!signup ? "page" : undefined}>Admin sign in</Link><Link to="/admin/signup" aria-current={signup ? "page" : undefined}>Admin sign up</Link></nav>
      <h2 id="admin-access-title">{signup ? "Create an admin account" : "Welcome back, admin."}</h2>
      <p>{signup ? checking ? "Checking account setup…" : requiresInvitation ? "Enter the invitation code provided by your shop owner." : "Create the first administrator account for this shop." : "Sign in to manage your shop’s appointments."}</p>
      {error && <div role="alert" className="admin-access__error">{error}</div>}
      {signup && !ready && !checking && <button type="button" className="btn btn-outline" onClick={() => setRefresh((previous) => previous + 1)}>Retry</button>}
      <form onSubmit={submit}>
        {signup && <label htmlFor="admin-name">Full name<input id="admin-name" name="name" autoComplete="name" maxLength={100} required value={fields.name} onChange={change} /></label>}
        <label htmlFor="admin-email">Work email<input id="admin-email" type="email" name="email" autoComplete="username" maxLength={150} required value={fields.email} onChange={change} /></label>
        <div className="admin-access__field"><label htmlFor="admin-password">Password</label><div className="admin-access__password"><input id="admin-password" type={visible ? "text" : "password"} name="password" autoComplete={signup ? "new-password" : "current-password"} minLength={signup ? 12 : undefined} maxLength={200} required value={fields.password} onChange={change} aria-describedby={signup ? "admin-password-hint" : undefined} /><button type="button" aria-label={visible ? "Hide password" : "Show password"} aria-pressed={visible} onClick={() => setVisible(!visible)}>{visible ? <EyeOff size={18} /> : <Eye size={18} />}</button></div>{signup && <small id="admin-password-hint">Use at least 12 characters.</small>}</div>
        {signup && <label htmlFor="admin-confirm">Confirm password<input id="admin-confirm" type={visible ? "text" : "password"} name="password_confirmation" autoComplete="new-password" minLength={12} maxLength={200} required value={fields.password_confirmation} onChange={change} /></label>}
        {signup && requiresInvitation && <label htmlFor="admin-invitation">Invitation code<input id="admin-invitation" name="invitation" autoComplete="off" minLength={64} maxLength={64} required value={fields.invitation} onChange={change} /><small>Invitations are specific to your email and expire after 48 hours.</small></label>}
        <button className="btn btn-primary admin-access__submit" disabled={busy || checking || !ready}>{busy ? "Please wait…" : signup ? "Create admin account" : "Sign in to dashboard"}<ArrowRight size={16} aria-hidden="true" /></button>
      </form>
      <p className="admin-access__switch">{signup ? "Already an administrator?" : "Need an admin account?"} <Link to={signup ? "/admin/signin" : "/admin/signup"}>{signup ? "Sign in" : "Sign up"}</Link></p>
      <Link className="admin-access__back" to="/">Back to the website</Link>
    </section>
  </main>;
}
