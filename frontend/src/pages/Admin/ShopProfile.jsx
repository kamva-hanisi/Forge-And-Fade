import { useEffect, useState } from "react";
import axios from "axios";
import { Link } from "react-router-dom";
import { useAdmin } from "../../context/AdminContext";
import { publicBusinessPath, shopPath } from "../../utils/shopLinks";

export default function ShopProfile({ client }) {
  const { setSession } = useAdmin();
  const [fields, setFields] = useState(null);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    client
      .get("/shop", { signal: controller.signal })
      .then(({ data }) => setFields(data))
      .catch((err) => {
        if (!axios.isCancel(err))
          setError("Unable to load your business details. Refresh the page to retry.");
      });
    return () => controller.abort();
  }, [client]);
  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const { data } = await client.patch("/shop", fields);
      setFields(data);
      setSession((previous) => ({
        ...previous,
        user: { ...previous.user, shop: { ...previous.user.shop, name: data.name } },
      }));
      setMessage("Your business details have been saved.");
    } catch (err) {
      setError(
        Object.values(err.response?.data?.errors || {})
          .flat()
          .join(" ") ||
          err.response?.data?.message ||
          "Unable to save. Please retry.",
      );
    } finally {
      setBusy(false);
    }
  };
  return (
    <section className="staff-card staff-invitations">
      <h2>Business details and location</h2>
      <p>Your phone number, street address and map pin appear on the public website.</p>
      {error && (
        <p className="admin-error" role="alert">
          {error}
        </p>
      )}
      {message && (
        <p className="admin-message" role="status">
          {message}
        </p>
      )}
      {fields && (
        <form className="staff-form" onSubmit={submit}>
          {[
            ["name", "Business name", 100],
            ["address", "Full street address", 255],
            ["phone", "Business phone", 30],
          ].map(([field, label, limit]) => (
            <label key={field}>
              {label}
              <input
                name={field}
                type={field === "phone" ? "tel" : "text"}
                autoComplete={field === "phone" ? "tel" : undefined}
                maxLength={limit}
                placeholder={
                  field === "address" ? "Street, suburb, city and postal code" : undefined
                }
                required={field === "name"}
                disabled={busy}
                value={fields[field] || ""}
                onChange={(event) => setFields({ ...fields, [field]: event.target.value })}
              />
            </label>
          ))}
          <label>
            Google Maps pin link (optional)
            <input
              name="directions_url"
              type="url"
              maxLength={2048}
              disabled={busy}
              value={fields.directions_url || ""}
              onChange={(event) => setFields({ ...fields, directions_url: event.target.value })}
              aria-describedby="shop-directions-help"
            />
            <small id="shop-directions-help">
              Paste the share link from your business’s Google Maps pin. When blank, directions use
              your street address.
            </small>
          </label>
          <p className="staff-notice">
            Business identifier: {fields.slug}
            <br />
            Business timezone: {fields.timezone}
            <br />
            <a href={`#${shopPath(fields.slug)}`} target="_blank" rel="noreferrer">
              Open your public booking page
            </a>
            {" · "}
            <Link to={publicBusinessPath("/locations", fields.slug)}>View business location</Link>
          </p>
          <div className="staff-form-actions">
            <button className="btn btn-primary" disabled={busy}>
              {busy ? "Saving…" : "Save business details"}
            </button>
          </div>
        </form>
      )}
    </section>
  );
}
