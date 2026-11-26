import { useState } from "react";
import { Link, Navigate } from "react-router-dom";
import { ArrowRight } from "lucide-react";
import { useAdmin } from "../../context/AdminContext";
import FormGuard from "../../components/FormGuard/FormGuard";
import { formError } from "../../utils/formError";
import AdminAccessIntro from "./AdminAccessIntro";
import "./AdminAccess.scss";

export default function ShopSignup() {
  const { session, authenticate, busy } = useAdmin();
  const [fields, setFields] = useState({
    shop_name: "",
    shop_slug: "",
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    website: "",
  });
  const [error, setError] = useState("");
  if (session)
    return (
      <Navigate
        to={session.user.permissions?.includes("shop.manage") ? "/admin/management" : "/admin"}
        replace
      />
    );
  const change = (event) => {
    setFields((previous) => ({ ...previous, [event.target.name]: event.target.value }));
    setError("");
  };
  const submit = async (event) => {
    event.preventDefault();
    if (busy) return;
    setError("");
    if (fields.password !== fields.password_confirmation) {
      setError("The passwords do not match.");
      return;
    }
    try {
      await authenticate("register-shop", fields);
    } catch (err) {
      setError(formError(err));
    }
  };
  return (
    <main className="admin-access admin-access--branded admin-access--signup container">
      <AdminAccessIntro title="Your business." accent="Your own space.">
        Give your barbershop its own workspace to manage appointments and staff access.
      </AdminAccessIntro>
      <section className="admin-access__card" aria-labelledby="shop-signup-title">
        <h2 id="shop-signup-title">Register your shop</h2>
        <p>
          For owners opening a new barbershop. Create your shop and owner account, then invite your
          team.
        </p>
        {error && (
          <p className="admin-access__error" role="alert">
            {error}
          </p>
        )}
        <form onSubmit={submit}>
          <fieldset className="admin-access__fields admin-access__signup-fields" disabled={busy}>
            <FormGuard
              value={fields.website}
              onChange={(value) => setFields((previous) => ({ ...previous, website: value }))}
            />
            <label>
              Shop name
              <input
                name="shop_name"
                autoComplete="organization"
                maxLength={100}
                required
                value={fields.shop_name}
                onChange={change}
              />
            </label>
            <label>
              Shop identifier
              <input
                name="shop_slug"
                autoComplete="off"
                minLength={3}
                maxLength={80}
                pattern="[a-z0-9]+(-[a-z0-9]+)*"
                placeholder="your-barbershop"
                required
                value={fields.shop_slug}
                onChange={change}
              />
              <small>
                Use lowercase letters, numbers and hyphens. Each shop needs a unique identifier.
              </small>
            </label>
            <label>
              Your full name
              <input
                name="name"
                autoComplete="name"
                maxLength={100}
                required
                value={fields.name}
                onChange={change}
              />
            </label>
            <label>
              Work email
              <input
                name="email"
                type="email"
                autoComplete="username"
                maxLength={150}
                required
                value={fields.email}
                onChange={change}
              />
            </label>
            <label>
              Password
              <input
                name="password"
                type="password"
                autoComplete="new-password"
                minLength={12}
                maxLength={200}
                required
                value={fields.password}
                onChange={change}
              />
              <small>Use at least 12 characters.</small>
            </label>
            <label>
              Confirm password
              <input
                name="password_confirmation"
                type="password"
                autoComplete="new-password"
                minLength={12}
                maxLength={200}
                required
                value={fields.password_confirmation}
                onChange={change}
              />
            </label>
            <button className="btn btn-primary admin-access__submit" disabled={busy}>
              {busy ? "Creating your shop…" : "Create shop account"}
              <ArrowRight size={16} aria-hidden="true" />
            </button>
          </fieldset>
        </form>
        <div className="admin-access__signup-footer">
          <p>
            Already registered? <Link to="/admin/signin">Sign in</Link>
          </p>
          <Link to="/admin/signup">Staff sign up with an invitation</Link>
        </div>
      </section>
    </main>
  );
}
