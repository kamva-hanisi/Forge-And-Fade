import { useEffect, useState } from "react";
import { Link, Navigate, useNavigate, useSearchParams } from "react-router-dom";
import { ArrowRight } from "lucide-react";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import PasswordField from "../../components/PasswordField/PasswordField";
import AdminAccessIntro from "./AdminAccessIntro";
import "./AdminAccess.scss";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

export default function AdminAccess({ signup = false, superAdminInvite = false }) {
  const { session, authenticate, busy } = useAdmin();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const staffSignup = signup && !superAdminInvite;
  const [fields, setFields] = useState({
    name: "",
    email: signup ? params.get("email") || "" : "",
    password: "",
    password_confirmation: "",
    invitation: signup ? params.get("invitation") || "" : "",
  });
  const [requiresInvitation, setRequiresInvitation] = useState(true);
  const [checking, setChecking] = useState(staffSignup);
  const [ready, setReady] = useState(!staffSignup);
  const [refresh, setRefresh] = useState(0);
  const [error, setError] = useState("");
  const firstAdminSetup = staffSignup && ready && !requiresInvitation;
  const invalidLink =
    superAdminInvite && (!fields.email || !/^[a-zA-Z0-9]{64}$/.test(fields.invitation));
  const mode = superAdminInvite ? "register-super-admin" : signup ? "register" : "login";

  useEffect(() => {
    if (!staffSignup) return;
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
  }, [staffSignup, refresh]);

  if (session) return <Navigate to="/admin" replace />;

  const change = (event) => {
    setFields((previous) => ({ ...previous, [event.target.name]: event.target.value }));
    setError("");
  };
  const submit = async (event) => {
    event.preventDefault();
    if (busy || invalidLink) return;
    setError("");
    if (signup && fields.password !== fields.password_confirmation) {
      setError("The passwords do not match.");
      return;
    }
    try {
      await authenticate(
        mode,
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
      <AdminAccessIntro title="Your business." accent="Your schedule.">
        One place to manage appointments, look after your team, and keep the day running smoothly.
      </AdminAccessIntro>
      <section className="admin-access__card" aria-labelledby="admin-access-title">
        <nav aria-label="Admin account access">
          <Link to="/admin/signin" aria-current={!signup ? "page" : undefined}>
            Admin sign in
          </Link>
          <Link to="/admin/signup" aria-current={staffSignup ? "page" : undefined}>
            Staff sign up
          </Link>
        </nav>
        <h2 id="admin-access-title">
          {superAdminInvite
            ? "Accept Super Admin invitation"
            : signup
              ? firstAdminSetup
                ? "Set up the first admin"
                : "Staff sign up"
              : "Welcome back."}
        </h2>
        <p>
          {superAdminInvite
            ? "Set up your account to accept the business access selected by the Website Owner."
            : signup
              ? checking
                ? "Checking account setup…"
                : firstAdminSetup
                  ? "Create the first Business Owner account for the existing business."
                  : "For staff invited to an existing business. Use your work email and the invitation code from your Business Owner."
              : "Website administrators, Business Owners, and staff sign in here."}
        </p>
        {staffSignup && (
          <p>
            Setting up a business? <Link to="/admin/register-shop">Register your business</Link>
          </p>
        )}
        {(invalidLink || error) && (
          <div role="alert" className="admin-access__error">
            {invalidLink
              ? "This invitation link is incomplete. Ask the Website Owner for a new link."
              : error}
          </div>
        )}
        {staffSignup && !ready && !checking && (
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
              readOnly={superAdminInvite}
              value={fields.email}
              onChange={change}
            />
          </label>
          <PasswordField
            label="Password"
            name="password"
            autoComplete={signup ? "new-password" : "current-password"}
            minLength={signup ? 12 : undefined}
            value={fields.password}
            onChange={change}
            showStrength
            hint={signup ? "Use at least 12 characters." : undefined}
          />
          {signup && (
            <PasswordField
              label="Confirm password"
              name="password_confirmation"
              autoComplete="new-password"
              minLength={12}
              value={fields.password_confirmation}
              onChange={change}
            />
          )}
          {staffSignup && requiresInvitation && (
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
            disabled={busy || checking || !ready || invalidLink}
          >
            {busy
              ? "Please wait…"
              : superAdminInvite
                ? "Create Super Admin account"
                : signup
                  ? firstAdminSetup
                    ? "Create owner account"
                    : "Join your business"
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
          </div>
        ) : (
          <p className="admin-access__registration">
            Use the email address associated with your account.
          </p>
        )}
      </section>
    </main>
  );
}
