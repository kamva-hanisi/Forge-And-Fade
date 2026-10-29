import { useEffect, useMemo, useState } from "react";
import axios from "axios";
import { Navigate } from "react-router-dom";
import { useAdmin } from "../../context/AdminContext";
import "./Admin.scss";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
const emptyFilters = {
  date_from: "",
  date_to: "",
  barber_id: "",
  status: "",
  search: "",
};
const errorText = (error) =>
  Object.values(error.response?.data?.errors || {})
    .flat()
    .join(" ") ||
  error.response?.data?.message ||
  "Unable to reach the server. Please try again.";

function Appointment({ booking, barbers, busy, onUpdate }) {
  const [editing, setEditing] = useState(false);
  const [confirmCancel, setConfirmCancel] = useState(false);
  const [schedule, setSchedule] = useState({
    barber_id: booking.barber_id,
    booking_date: booking.booking_date,
    booking_time: booking.booking_time.slice(0, 5),
  });

  return (
    <article className="admin-appointment">
      <header>
        <h2>{booking.customer_name}</h2>
        <span className={`admin-badge admin-badge--${booking.status}`}>{booking.status}</span>
      </header>
      <p>
        <strong>{booking.service_name}</strong> · {booking.duration} min
      </p>
      <p>
        {booking.booking_date} at {booking.booking_time.slice(0, 5)} · {booking.barber_name}
      </p>
      <p>
        <a href={`mailto:${booking.email}`}>{booking.email}</a> ·{" "}
        <a href={`tel:${booking.phone}`}>{booking.phone}</a>
      </p>
      {booking.notes && <p className="admin-notes">{booking.notes}</p>}
      {booking.status === "confirmed" && (
        <div className="admin-actions">
          <button
            type="button"
            className="btn btn-outline"
            disabled={busy}
            onClick={() => {
              setEditing(!editing);
              setConfirmCancel(false);
            }}
          >
            Reschedule
          </button>
          <button
            type="button"
            className="btn btn-outline"
            disabled={busy}
            onClick={() => {
              setConfirmCancel(true);
              setEditing(false);
            }}
          >
            Cancel appointment
          </button>
        </div>
      )}
      {confirmCancel && (
        <div className="admin-confirm" role="group" aria-label="Confirm cancellation">
          <p>Cancel this appointment? Its time will become available for another customer.</p>
          <button
            type="button"
            className="btn btn-primary"
            disabled={busy}
            onClick={() => onUpdate(booking, { action: "cancel" })}
          >
            Yes, cancel appointment
          </button>
          <button
            type="button"
            className="btn btn-outline"
            disabled={busy}
            onClick={() => setConfirmCancel(false)}
          >
            Keep appointment
          </button>
        </div>
      )}
      {editing && (
        <form
          className="admin-schedule"
          onSubmit={(event) => {
            event.preventDefault();
            onUpdate(booking, { action: "reschedule", ...schedule });
          }}
        >
          <label>
            Barber
            <select
              required
              value={schedule.barber_id}
              onChange={(event) => setSchedule({ ...schedule, barber_id: event.target.value })}
            >
              {barbers.map((barber) => (
                <option key={barber.id} value={barber.id}>
                  {barber.name}
                </option>
              ))}
            </select>
          </label>
          <label>
            New date
            <input
              type="date"
              required
              value={schedule.booking_date}
              onChange={(event) => setSchedule({ ...schedule, booking_date: event.target.value })}
            />
          </label>
          <label>
            New time
            <input
              type="time"
              required
              step="600"
              value={schedule.booking_time}
              onChange={(event) => setSchedule({ ...schedule, booking_time: event.target.value })}
            />
          </label>
          <p>
            The full {booking.duration}-minute service must fit opening hours and breaks. All times
            are South Africa time. Availability is checked when you save.
          </p>
          <button className="btn btn-primary" disabled={busy}>
            {busy ? "Saving…" : "Save new time"}
          </button>
        </form>
      )}
    </article>
  );
}

export default function Admin() {
  const { session, setSession } = useAdmin();
  const token = session?.token || "";
  const [filters, setFilters] = useState(emptyFilters);
  const [query, setQuery] = useState({ ...emptyFilters, page: 1 });
  const [result, setResult] = useState(null);
  const [barbers, setBarbers] = useState([]);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [refresh, setRefresh] = useState(0);
  const client = useMemo(
    () =>
      axios.create({
        baseURL: apiUrl,
        headers: token
          ? { Authorization: `Bearer ${token}`, Accept: "application/json" }
          : { Accept: "application/json" },
        timeout: 20000,
      }),
    [token],
  );

  useEffect(() => {
    if (!token) return;
    const controller = new AbortController();
    const load = async () => {
      setLoading(true);
      setError("");
      setResult(null);
      try {
        const [bookings, catalog] = await Promise.all([
          client.get("/admin/bookings", {
            params: query,
            signal: controller.signal,
          }),
          client.get("/bookings/catalog", { signal: controller.signal }),
        ]);
        setResult(bookings.data);
        setBarbers(catalog.data.barbers);
      } catch (err) {
        if (!axios.isCancel(err)) {
          if (err.response?.status === 401) setSession(null);
          setError(errorText(err));
        }
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    };
    load();
    return () => controller.abort();
  }, [token, client, query, refresh, setSession]);

  const update = async (booking, changes) => {
    setBusy(true);
    setError("");
    setMessage("");
    try {
      await client.patch(`/admin/bookings/${booking.id}`, {
        ...changes,
        revision: booking.revision,
      });
      setMessage(
        changes.action === "cancel"
          ? "Appointment cancelled. The time is available again."
          : "Appointment rescheduled.",
      );
      setRefresh((previous) => previous + 1);
    } catch (err) {
      if (err.response?.status === 401) {
        setSession(null);
        setResult(null);
      }
      setError(errorText(err));
    } finally {
      setBusy(false);
    }
  };

  if (!token) return <Navigate to="/admin/signin" replace />;

  return (
    <main className="admin-page container">
      <div className="admin-heading">
        <div>
          <span className="section-label">Forge & Fade · Schedule</span>
          <h1 className="section-title">Appointments</h1>
          <p>Manage bookings, reschedule visits, and keep your day running smoothly.</p>
        </div>
      </div>
      {error && (
        <p role="alert" className="admin-error">
          {error}
        </p>
      )}
      {message && (
        <p role="status" className="admin-success">
          {message}
        </p>
      )}
      <>
        <form
          className="admin-filters"
          onSubmit={(event) => {
            event.preventDefault();
            setQuery({ ...filters, page: 1 });
          }}
        >
          <label>
            From
            <input
              type="date"
              value={filters.date_from}
              onChange={(event) => setFilters({ ...filters, date_from: event.target.value })}
            />
          </label>
          <label>
            To
            <input
              type="date"
              value={filters.date_to}
              onChange={(event) => setFilters({ ...filters, date_to: event.target.value })}
            />
          </label>
          <label>
            Barber
            <select
              value={filters.barber_id}
              onChange={(event) => setFilters({ ...filters, barber_id: event.target.value })}
            >
              <option value="">All barbers</option>
              {barbers.map((barber) => (
                <option key={barber.id} value={barber.id}>
                  {barber.name}
                </option>
              ))}
            </select>
          </label>
          <label>
            Status
            <select
              value={filters.status}
              onChange={(event) => setFilters({ ...filters, status: event.target.value })}
            >
              <option value="">All statuses</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </label>
          <label>
            Customer
            <input
              type="search"
              placeholder="Name or email"
              value={filters.search}
              maxLength={100}
              onChange={(event) => setFilters({ ...filters, search: event.target.value })}
            />
          </label>
          <div className="admin-filter-actions">
            <button className="btn btn-primary" disabled={busy || loading}>
              Apply filters
            </button>
            <button
              type="button"
              className="btn btn-outline"
              disabled={busy || loading}
              onClick={() => {
                setFilters(emptyFilters);
                setQuery({ ...emptyFilters, page: 1 });
              }}
            >
              Reset
            </button>
          </div>
        </form>
        <div className="admin-toolbar">
          <p>
            {loading
              ? "Loading appointments…"
              : result
                ? `${result.total} appointment${result.total === 1 ? "" : "s"} · South Africa time`
                : "Appointments unavailable"}
          </p>
          <button
            type="button"
            className="btn btn-outline"
            disabled={busy || loading}
            onClick={() => setRefresh((previous) => previous + 1)}
          >
            Refresh
          </button>
        </div>
        {result?.data.length === 0 && <p role="status">No appointments match your filters.</p>}
        <div className="admin-list">
          {result?.data.map((booking) => (
            <Appointment
              key={`${booking.id}-${booking.revision}`}
              booking={booking}
              barbers={barbers}
              busy={busy || loading}
              onUpdate={update}
            />
          ))}
        </div>
        {result && result.last_page > 1 && (
          <nav className="admin-pagination" aria-label="Appointment pages">
            <button
              type="button"
              className="btn btn-outline"
              disabled={busy || loading || result.current_page === 1}
              onClick={() => setQuery({ ...query, page: query.page - 1 })}
            >
              Previous
            </button>
            <span>
              Page {result.current_page} of {result.last_page}
            </span>
            <button
              type="button"
              className="btn btn-outline"
              disabled={busy || loading || result.current_page === result.last_page}
              onClick={() => setQuery({ ...query, page: query.page + 1 })}
            >
              Next
            </button>
          </nav>
        )}
      </>
    </main>
  );
}
