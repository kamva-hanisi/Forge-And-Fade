import { useEffect, useMemo, useRef, useState } from "react";
import { Link, Navigate } from "react-router-dom";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import { formError } from "../../utils/formError";

const empty = {
  service_id: "",
  barber_id: "",
  booking_date: "",
  booking_time: "",
  customer_name: "",
  email: "",
  phone: "",
  notes: "",
};
const money = (value) =>
  new Intl.NumberFormat("en-ZA", { style: "currency", currency: "ZAR" }).format(Number(value));

export default function AdminBooking() {
  const { session, setSession } = useAdmin();
  const allowed = session.user.permissions?.includes("bookings.write");
  const [fields, setFields] = useState(empty);
  const [catalog, setCatalog] = useState(null);
  const [catalogError, setCatalogError] = useState("");
  const [catalogRefresh, setCatalogRefresh] = useState(0);
  const [availability, setAvailability] = useState({ key: "", times: [], error: "" });
  const [refresh, setRefresh] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [receipt, setReceipt] = useState(null);
  const submitting = useRef(false);
  const successHeading = useRef(null);
  const client = useMemo(
    () =>
      axios.create({
        baseURL: `${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/admin`,
        headers: { Authorization: `Bearer ${session.token}`, Accept: "application/json" },
        timeout: 20000,
      }),
    [session.token],
  );
  const service = catalog?.services.find((item) => item.id === Number(fields.service_id));
  const ready = Boolean(catalog && service && fields.barber_id && fields.booking_date);
  const key = [fields.service_id, fields.barber_id, fields.booking_date, refresh].join("/");
  const times = availability.key === key ? availability.times : [];
  const checking = ready && availability.key !== key;
  const slotError = availability.key === key ? availability.error : "";
  const validTime = ready && !checking && !slotError && times.includes(fields.booking_time);
  const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: catalog?.timezone || "Africa/Johannesburg",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date());

  useEffect(() => {
    if (!allowed) return;
    const controller = new AbortController();
    client
      .get("/bookings/catalog", { signal: controller.signal })
      .then(({ data }) => {
        setCatalog(data);
        setCatalogError("");
      })
      .catch((err) => {
        if (!axios.isCancel(err)) {
          if (err.response?.status === 401) setSession(null);
          else {
            setCatalog(null);
            setCatalogError(formError(err));
          }
        }
      });
    return () => controller.abort();
  }, [allowed, client, catalogRefresh, setSession]);

  useEffect(() => {
    if (!allowed || !ready || receipt) return;
    const controller = new AbortController();
    client
      .get("/bookings/availability", {
        params: {
          service_id: fields.service_id,
          barber_id: fields.barber_id,
          booking_date: fields.booking_date,
        },
        signal: controller.signal,
      })
      .then(({ data }) => setAvailability({ key, times: data.available_times, error: "" }))
      .catch((err) => {
        if (!axios.isCancel(err)) {
          if (err.response?.status === 401) setSession(null);
          else setAvailability({ key, times: [], error: formError(err) });
        }
      });
    return () => controller.abort();
  }, [
    allowed,
    ready,
    receipt,
    client,
    fields.service_id,
    fields.barber_id,
    fields.booking_date,
    key,
    setSession,
  ]);

  useEffect(() => {
    if (receipt) successHeading.current?.focus();
  }, [receipt]);
  const change = (event) => {
    const { name, value } = event.target;
    setFields((previous) => ({
      ...previous,
      [name]: value,
      ...(["service_id", "barber_id", "booking_date"].includes(name) ? { booking_time: "" } : {}),
    }));
    setError("");
  };
  const submit = async (event) => {
    event.preventDefault();
    if (submitting.current) return;
    if (!validTime) {
      setError("Choose a currently available appointment time.");
      return;
    }
    submitting.current = true;
    setBusy(true);
    setError("");
    try {
      const { data } = await client.post("/bookings", { ...fields, quoted_price: service.price });
      setReceipt(data.booking);
    } catch (err) {
      if (err.response?.status === 401) setSession(null);
      else {
        setError(
          err.response
            ? formError(err)
            : "We could not confirm whether the appointment was saved. Check the appointments list before trying again.",
        );
        if (err.response?.status === 409) {
          setFields((previous) => ({ ...previous, booking_time: "" }));
          setRefresh((value) => value + 1);
        }
        if (err.response?.data?.errors?.quoted_price) {
          setCatalog(null);
          setCatalogRefresh((value) => value + 1);
        }
      }
    } finally {
      submitting.current = false;
      setBusy(false);
    }
  };

  if (!allowed) return <Navigate to="/admin/appointments" replace />;
  return (
    <main className="staff-page staff-manual-booking">
      <header className="staff-page-heading">
        <span className="section-label">{session.user.shop?.name} · Schedule</span>
        <h1 className="section-title">Add appointment</h1>
        <p>
          Book on behalf of a customer. Normal prices, opening hours, breaks and availability still
          apply.
        </p>
      </header>
      {receipt ? (
        <section className="staff-card">
          <h2 tabIndex={-1} ref={successHeading}>
            Appointment created
          </h2>
          <p role="status">
            Booking reference: <strong>{receipt.reference}</strong>
          </p>
          <p>
            {receipt.customer_name} · {receipt.service_name} · {receipt.barber_name}
          </p>
          <p>
            {receipt.booking_date.slice(0, 10)} at {receipt.booking_time.slice(0, 5)} ·{" "}
            {receipt.timezone}
          </p>
          <p>
            {money(receipt.price)} · {receipt.duration} minutes. No confirmation email has been
            sent.
          </p>
          <div className="staff-form-actions">
            <Link className="btn btn-primary" to="/admin/appointments">
              View appointments
            </Link>
            <button
              type="button"
              className="btn btn-outline"
              onClick={() => {
                setFields(empty);
                setReceipt(null);
                setRefresh((value) => value + 1);
              }}
            >
              Add another appointment
            </button>
          </div>
        </section>
      ) : (
        <section className="staff-card">
          <h2>Appointment details</h2>
          <p>
            Times are shown in {catalog?.timezone || "Africa/Johannesburg"}. Do not record sensitive
            customer information in notes.
          </p>
          {catalogError && (
            <div className="admin-error" role="alert">
              {catalogError}
              <button
                type="button"
                className="btn btn-outline"
                onClick={() => {
                  setCatalogError("");
                  setCatalogRefresh((value) => value + 1);
                }}
              >
                Retry services
              </button>
            </div>
          )}
          {!catalog && !catalogError && <p role="status">Loading services and barbers…</p>}
          {error && (
            <p role="alert" className="admin-error">
              {error}
            </p>
          )}
          {catalog && (
            <form className="staff-form" onSubmit={submit}>
              <fieldset className="staff-booking-fields" disabled={busy}>
                <label>
                  Service
                  <select name="service_id" required value={fields.service_id} onChange={change}>
                    <option value="">Choose a service</option>
                    {catalog.services.map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.name} · {money(item.price)} · {item.duration} min
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  Barber
                  <select name="barber_id" required value={fields.barber_id} onChange={change}>
                    <option value="">Choose a barber</option>
                    {catalog.barbers.map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  Date
                  <input
                    name="booking_date"
                    type="date"
                    required
                    min={today}
                    value={fields.booking_date}
                    onChange={change}
                  />
                </label>
                {checking && <p role="status">Checking available times…</p>}
                {slotError && (
                  <p className="admin-error" role="alert">
                    {slotError}
                  </p>
                )}
                {ready && !checking && !slotError && times.length === 0 && (
                  <p role="status">
                    No available times for this date. Choose another date or barber.
                  </p>
                )}
                <label>
                  Available time
                  <select
                    name="booking_time"
                    required
                    disabled={!ready || checking || Boolean(slotError)}
                    value={fields.booking_time}
                    onChange={change}
                  >
                    <option value="">Choose an available time</option>
                    {times.map((time) => (
                      <option key={time} value={time}>
                        {time}
                      </option>
                    ))}
                  </select>
                </label>
                {ready && (
                  <button
                    type="button"
                    className="btn btn-outline"
                    onClick={() => {
                      setFields((previous) => ({ ...previous, booking_time: "" }));
                      setRefresh((value) => value + 1);
                    }}
                    disabled={checking}
                  >
                    Refresh availability
                  </button>
                )}
                <label>
                  Customer name
                  <input
                    name="customer_name"
                    required
                    maxLength={100}
                    autoComplete="off"
                    value={fields.customer_name}
                    onChange={change}
                  />
                </label>
                <label>
                  Customer email
                  <input
                    name="email"
                    type="email"
                    required
                    maxLength={150}
                    autoComplete="off"
                    value={fields.email}
                    onChange={change}
                  />
                </label>
                <label>
                  Customer phone
                  <input
                    name="phone"
                    type="tel"
                    required
                    maxLength={30}
                    autoComplete="off"
                    value={fields.phone}
                    onChange={change}
                  />
                </label>
                <label>
                  Notes (optional)
                  <textarea
                    name="notes"
                    maxLength={1000}
                    rows={3}
                    value={fields.notes}
                    onChange={change}
                  />
                </label>
                {service && (
                  <p>
                    Saved price: <strong>{money(service.price)}</strong> · {service.duration}{" "}
                    minutes. Payment is not collected here.
                  </p>
                )}
                <div className="staff-form-actions">
                  <Link className="btn btn-outline" to="/admin/appointments">
                    Back to appointments
                  </Link>
                  <button className="btn btn-primary" disabled={busy || !validTime}>
                    {busy ? "Creating appointment…" : "Create appointment"}
                  </button>
                </div>
              </fieldset>
            </form>
          )}
        </section>
      )}
    </main>
  );
}
