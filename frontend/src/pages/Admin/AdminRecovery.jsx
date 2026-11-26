import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import axios from "axios";
import { ArrowLeft, ArrowRight } from "lucide-react";
import { formError } from "../../utils/formError";
import { useAdmin } from "../../context/AdminContext";
import AdminAccessIntro from "./AdminAccessIntro";
import "./AdminAccess.scss";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

export default function AdminRecovery({ reset = false }) {
  const { setSession } = useAdmin();
  const [params] = useSearchParams();
  const [email, setEmail] = useState(reset ? params.get("email") || "" : "");
  const [token] = useState(params.get("token") || "");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const invalidLink = reset && (!email || !/^[a-f0-9]{64}$/i.test(token));

  const submit = async (event) => {
    event.preventDefault();
    if (busy) return;
    setError("");
    setMessage("");
    if (reset && password !== confirmation) {
      setError("The passwords do not match.");
      return;
    }
    setBusy(true);
    try {
      const { data } = await axios.post(
        `${apiUrl}/admin/${reset ? "reset-password" : "forgot-password"}`,
        reset ? { email, token, password, password_confirmation: confirmation } : { email },
        { timeout: 20000 },
      );
      setPassword("");
      setConfirmation("");
      if (reset) setSession(null);
      setMessage(data.message);
    } catch (err) {
      setError(formError(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <main className="admin-access admin-access--branded admin-access--recovery container">
      <AdminAccessIntro title="Get back" accent="to your shop.">
        Recover access to the tools you use to manage appointments and run your shop.
      </AdminAccessIntro>
      <section className="admin-access__card" aria-labelledby="recovery-title">
        <h2 id="recovery-title">{reset ? "Choose a new password" : "Forgot your password?"}</h2>
        <p>
          {reset
            ? "Recovery links expire after 60 minutes and can only be used once. Resetting signs you out on every device."
            : "Enter your work email. If it matches an admin account, we’ll send you a recovery link."}
        </p>
        {invalidLink && (
          <div className="admin-access__error" role="alert">
            This recovery link is incomplete. Request a new link below.
          </div>
        )}
        {error && (
          <div className="admin-access__error" role="alert">
            {error}
          </div>
        )}
        {message && <p role="status">{message}</p>}
        {!invalidLink && !(reset && message) && (
          <form onSubmit={submit}>
            <fieldset className="admin-access__fields" disabled={busy}>
              <label htmlFor="recovery-email">
                Work email
                <input
                  id="recovery-email"
                  type="email"
                  autoComplete="username"
                  maxLength={150}
                  required
                  readOnly={reset}
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                />
              </label>
              {reset && (
                <>
                  <label htmlFor="recovery-password">
                    New password
                    <input
                      id="recovery-password"
                      type="password"
                      autoComplete="new-password"
                      minLength={12}
                      maxLength={200}
                      required
                      value={password}
                      onChange={(event) => setPassword(event.target.value)}
                      aria-describedby="recovery-password-hint"
                    />
                    <small id="recovery-password-hint">Use at least 12 characters.</small>
                  </label>
                  <label htmlFor="recovery-confirm">
                    Confirm new password
                    <input
                      id="recovery-confirm"
                      type="password"
                      autoComplete="new-password"
                      minLength={12}
                      maxLength={200}
                      required
                      value={confirmation}
                      onChange={(event) => setConfirmation(event.target.value)}
                    />
                  </label>
                </>
              )}
              <button className="btn btn-primary admin-access__submit" disabled={busy}>
                {busy ? "Please wait…" : reset ? "Reset password" : "Send recovery link"}
                <ArrowRight size={16} aria-hidden="true" />
              </button>
            </fieldset>
          </form>
        )}
        {reset && !message && (
          <p>
            <Link to="/admin/forgot-password">Request a new recovery link</Link>
          </p>
        )}
        <Link className="admin-access__back" to="/admin/signin">
          <ArrowLeft size={16} aria-hidden="true" /> Back to admin sign in
        </Link>
      </section>
    </main>
  );
}
