import { useEffect, useState } from "react";
import { Link, Navigate, useNavigate, useSearchParams } from "react-router-dom";
import { ArrowRight, Eye, EyeOff } from "lucide-react";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import AdminAccessIntro from "./AdminAccessIntro";
import "./AdminAccess.scss";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

export default function AdminAccess({ signup = false }) {
  const { session, authenticate, busy } = useAdmin();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [fields, setFields] = useState({
    name: "",
    email: signup ? params.get("email") || "" : "",
    password: "",
    password_confirmation: "",
    invitation: signup ? params.get("invitation") || "" : "",
  });
  const [requiresInvitation, setRequiresInvitation] = useState(true);
  const [checking, setChecking] = useState(signup);
  const [ready, setReady] = useState(!signup);
  const [refresh, setRefresh] = useState(0);
  const [visible, setVisible] = useState(false);
  const [error, setError] = useState("");
  const firstAdminSetup = signup && ready && !requiresInvitation;

  useEffect(() => {
    if (!signup) return;
    const controller = new AbortController();
    const check = async () => {
      setChecking(true);
      setReady(false);
      setError("");
      try {
        const { data } = await axios.get(`${apiUrl}/admin/registration`, {
          signal: controller.signal,
          timeout: 20000,
        });
        setRequiresInvitation(data.requires_invitation);
        setReady(true);
      } catch (err) {
        if (!axios.isCancel(err)) setError("We could not check admin registration. Please retry.");
      } finally {
        if (!controller.signal.aborted) setChecking(false);
      }
    };
    check();
    return () => controller.abort();
  }, [signup, refresh]);

  if (session) return <Navigate to="/admin" replace />;

  const change = (event) => {
    setFields((previous) => ({ ...previous, [event.target.name]: event.target.value }));
    setError("");
  };
  const submit = async (event) => {
    event.preventDefault();
    setError("");
    if (signup && fields.password !== fields.password_confirmation) {
      setError("The passwords do not match.");
      return;
    }
    try {
      await authenticate(
        signup ? "register" : "login",
        signup ? fields : { email: fields.email, password: fields.password },
      );
      navigate("/admin", { replace: true });
    } catch (err) {
      const errors = err.response?.data?.errors;
      if (errors?.invitation) setRequiresInvitation(true);
      setError(
        err.response?.status === 429
          ? "Too many attempts. Wait a minute and try again."
          : Object.values(errors || {})
              .flat()
              .join(" ") ||
              err.response?.data?.message ||
              "Unable to connect. Please try again.",
      );
    }
  };

  return (
    <main
      className={`admin-access admin-access--branded container${signup ? " admin-access--signup" : ""}`}
    >
      <AdminAccessIntro title="Your shop." accent="Your schedule.">
        One place to manage appointments, look after your team, and keep the day running smoothly.
      </AdminAccessIntro>
      <section className="admin-access__card" aria-labelledby="admin-access-title">
        <nav aria-label="Admin account access">
          <Link to="/admin/signin" aria-current={!signup ? "page" : undefined}>
            Admin sign in
          </Link>
          <Link to="/admin/signup" aria-current={signup ? "page" : undefined}>
            Staff sign up
          </Link>
        </nav>
        <h2 id="admin-access-title">
          {signup
            ? firstAdminSetup
              ? "Set up the first admin"
              : "Staff sign up"
            : "Welcome back."}
        </h2>
        <p>
          {signup
            ? checking
              ? "Checking account setup…"
              : firstAdminSetup
                ? "Create the first owner account for the existing Forge & Fade shop."
                : "For staff invited to an existing shop. Use the work email and invitation code from your shop owner."
            : "Shop owners and staff can sign in here to access their dashboard."}
        </p>
        {signup && (
          <p>
            Opening a new barbershop? <Link to="/admin/register-shop">Register your shop</Link>
          </p>
        )}
        {error && (
          <div role="alert" className="admin-access__error">
            {error}
          </div>
        )}
        {signup && !ready && !checking && (
          <button
            type="button"
            className="btn btn-outline"
            onClick={() => setRefresh((previous) => previous + 1)}
          >
            Retry
          </button>
        )}
        <form className={signup ? "admin-access__signup-fields" : undefined} onSubmit={submit}>
          {signup && (
            <label htmlFor="admin-name">
              Full name
              <input
                id="admin-name"
                name="name"
                autoComplete="name"
                maxLength={100}
                required
                value={fields.name}
                onChange={change}
              />
            </label>
          )}
          <label htmlFor="admin-email">
            Work email
            <input
              id="admin-email"
              type="email"
              name="email"
              autoComplete="username"
              maxLength={150}
              required
              value={fields.email}
              onChange={change}
            />
          </label>
          <div className="admin-access__field">
            <label htmlFor="admin-password">Password</label>
            <div className="admin-access__password">
              <input
                id="admin-password"
                type={visible ? "text" : "password"}
                name="password"
                autoComplete={signup ? "new-password" : "current-password"}
                minLength={signup ? 12 : undefined}
                maxLength={200}
                required
                value={fields.password}
                onChange={change}
                aria-describedby={signup ? "admin-password-hint" : undefined}
              />
              <button
                type="button"
                aria-label={visible ? "Hide password" : "Show password"}
                aria-pressed={visible}
                onClick={() => setVisible(!visible)}
              >
                {visible ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
            {signup && <small id="admin-password-hint">Use at least 12 characters.</small>}
          </div>
          {signup && (
            <label htmlFor="admin-confirm">
              Confirm password
              <input
                id="admin-confirm"
                type={visible ? "text" : "password"}
                name="password_confirmation"
                autoComplete="new-password"
                minLength={12}
                maxLength={200}
                required
                value={fields.password_confirmation}
                onChange={change}
              />
            </label>
          )}
          {signup && requiresInvitation && (
            <label className="admin-access__invitation" htmlFor="admin-invitation">
              Invitation code
              <input
                id="admin-invitation"
                name="invitation"
                autoComplete="off"
                minLength={64}
                maxLength={64}
                required
                value={fields.invitation}
                onChange={change}
              />
              <small>Invitations are specific to your email and expire after 48 hours.</small>
            </label>
          )}
          <button
            className="btn btn-primary admin-access__submit"
            disabled={busy || checking || !ready}
          >
            {busy
              ? "Please wait…"
              : signup
                ? firstAdminSetup
                  ? "Create owner account"
                  : "Join your shop"
                : "Sign in to dashboard"}
            <ArrowRight size={16} aria-hidden="true" />
          </button>
        </form>
        {!signup && (
          <p className="admin-access__recovery">
            <Link to="/admin/forgot-password">Forgot your password?</Link>
          </p>
        )}
        {signup ? (
          <div className="admin-access__signup-footer">
            <p>
              Already have an account? <Link to="/admin/signin">Sign in</Link>
            </p>
            <Link to="/">Back to the website</Link>
          </div>
        ) : (
          <p className="admin-access__registration">
            Opening a new barbershop? <Link to="/admin/register-shop">Register your shop</Link>
          </p>
        )}
      </section>
    </main>
  );
}
