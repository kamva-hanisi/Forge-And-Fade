import { useEffect, useState } from "react";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import { shopPath } from "../../utils/shopLinks";

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
          setError("Unable to load your shop details. Refresh the page to retry.");
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
      setMessage("Your shop details have been saved.");
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
      <h2>Your shop</h2>
      <p>
        Owner-only business details. The identifier and timezone stay fixed to protect existing
        bookings.
      </p>
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
            ["name", "Shop name", 100],
            ["address", "Address", 255],
            ["phone", "Shop phone", 30],
          ].map(([field, label, limit]) => (
            <label key={field}>
              {label}
              <input
                name={field}
                maxLength={limit}
                required={field === "name"}
                disabled={busy}
                value={fields[field] || ""}
                onChange={(event) => setFields({ ...fields, [field]: event.target.value })}
              />
            </label>
          ))}
          <p className="staff-notice">
            Shop identifier: {fields.slug}
            <br />
            Business timezone: {fields.timezone}
            <br />
            <a href={`#${shopPath(fields.slug)}`} target="_blank" rel="noreferrer">
              Open your public booking page
            </a>
          </p>
          <div className="staff-form-actions">
            <button className="btn btn-primary" disabled={busy}>
              {busy ? "Saving…" : "Save shop details"}
            </button>
          </div>
        </form>
      )}
    </section>
  );
}
