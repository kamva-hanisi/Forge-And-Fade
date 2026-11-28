import { useEffect, useMemo, useState } from "react";
import axios from "axios";
import { adminHeaders } from "../../utils/adminHeaders";
import { Link, Navigate, useSearchParams } from "react-router-dom";
import { useAdmin } from "../../context/AdminContext";
import "./Admin.scss";
import PaymentPanel from "./PaymentPanel";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
const emptyFilters = {
  date_from: "",
  date_to: "",
  barber_id: "",
  status: "",
  email_status: "",
  search: "",
  view: "all",
};
const errorText = (error) =>
  Object.values(error.response?.data?.errors || {})
    .flat()
    .join(" ") ||
  error.response?.data?.message ||
  "Unable to reach the server. Please try again.";

const emailKinds = [
  ["confirmation", "Confirmation"],
  ["cancelled", "Cancellation"],
  ["reminder", "24-hour reminder"],
];
const emailStatuses = {
  sent: "Sent",
  failed: "Failed",
  pending: "Pending",
  skipped: "Skipped",
};
const appointmentPollInterval = 10000;

function EmailDelivery({ booking, busy, onRetry }) {
  if (!booking.email_deliveries) return null;

  const sentTime = new Intl.DateTimeFormat("en-ZA", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: booking.timezone || "Africa/Johannesburg",
  });

  return (
    <div className="admin-email-delivery">
      <h3>Email delivery</h3>
      <dl>
        {emailKinds.map(([kind, label]) => {
          const delivery = booking.email_deliveries.find((email) => email.kind === kind);
          const previousReminder =
            kind === "reminder" && delivery && delivery.revision < booking.revision;

          return (
            <div key={kind}>
              <dt>{label}</dt>
              <dd>
                <span
                  className={`admin-badge admin-email-delivery__status--${delivery?.status || "missing"}`}
                  aria-live="polite"
                >
                  {emailStatuses[delivery?.status] || "No record"}
                </span>
                {delivery?.sent_at && (
                  <time dateTime={delivery.sent_at}>
                    {sentTime.format(new Date(delivery.sent_at))}
                  </time>
                )}
                {delivery?.status === "failed" && (
                  <small>
                    {delivery.attempts} {delivery.attempts === 1 ? "attempt" : "attempts"}
                  </small>
                )}
                {delivery?.automatic_retry_scheduled && <small>Automatic retry scheduled</small>}
                {previousReminder && <small>Previous appointment version</small>}
                {delivery?.can_retry && (
                  <button
                    type="button"
                    className="admin-email-delivery__retry"
                    aria-label={`Retry ${label.toLowerCase()} email`}
                    disabled={busy}
                    onClick={() => onRetry(booking, delivery)}
                  >
                    Retry
                  </button>
                )}
              </dd>
            </div>
          );
        })}
      </dl>
    </div>
  );
}

function Appointment({ booking, barbers, busy, onUpdate, onRetryEmail, client, onPaymentSaved }) {
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
      <EmailDelivery booking={booking} busy={busy} onRetry={onRetryEmail} />
      <PaymentPanel booking={booking} client={client} onSaved={onPaymentSaved} />
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
          {booking.active_payment && (
            <p>A receipt is recorded. Cancelling does not refund money or void that receipt.</p>
          )}
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
  const canManageBookings = session?.user.permissions?.includes("bookings.write");
  const [params] = useSearchParams();
  const [filters, setFilters] = useState(() => ({
    ...emptyFilters,
    view: params.get("view") === "today" ? "today" : "all",
    status: params.get("status") === "confirmed" ? "confirmed" : "",
    email_status: canManageBookings && params.get("email_status") === "failed" ? "failed" : "",
  }));
  const [query, setQuery] = useState(() => ({ ...filters, page: 1 }));
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
        headers: token ? adminHeaders(session) : { Accept: "application/json" },
        timeout: 20000,
      }),
    [token, session],
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
          client.get("/admin/bookings", { params: query, signal: controller.signal }),
          client.get("/admin/bookings/catalog", { signal: controller.signal }),
        ]);
        if (controller.signal.aborted) return;
        if (bookings.data.current_page > bookings.data.last_page) {
          setQuery({ ...query, page: bookings.data.last_page });
          return;
        }
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

  useEffect(() => {
    if (!canManageBookings || busy || loading) return;
    const controller = new AbortController();
    let checking = false;

    const poll = async () => {
      if (document.hidden || checking || controller.signal.aborted) return;
      checking = true;
      try {
        const { data } = await client.get("/admin/bookings", {
          params: query,
          signal: controller.signal,
        });
        if (controller.signal.aborted) return;
        if (data.current_page > data.last_page) {
          setQuery({ ...query, page: data.last_page });
          return;
        }
        setResult(data);
      } catch (err) {
        if (controller.signal.aborted) return;
        if (err.response?.status === 401) {
          setSession(null);
        } else if (err.response?.status === 403) {
          controller.abort();
          setResult(null);
          setError(errorText(err));
        }
      } finally {
        checking = false;
      }
    };

    const timer = window.setInterval(poll, appointmentPollInterval);
    document.addEventListener("visibilitychange", poll);
    return () => {
      controller.abort();
      window.clearInterval(timer);
      document.removeEventListener("visibilitychange", poll);
    };
  }, [canManageBookings, client, query, refresh, busy, loading, setSession]);

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

  const retryEmail = async (booking, delivery) => {
    setBusy(true);
    setError("");
    setMessage("");
    try {
      const { data } = await client.post(
        `/admin/bookings/${booking.id}/emails/${delivery.kind}/retry`,
        { revision: delivery.revision },
      );
      setMessage(data.message);
      if (query.email_status === "failed") {
        setRefresh((previous) => previous + 1);
        return;
      }
      setResult(
        (current) =>
          current && {
            ...current,
            data: current.data.map((appointment) =>
              appointment.id === booking.id &&
              appointment.revision === booking.revision &&
              appointment.email_deliveries
                ? {
                    ...appointment,
                    email_deliveries: appointment.email_deliveries.map((email) =>
                      email.kind === delivery.kind && email.revision === delivery.revision
                        ? {
                            ...email,
                            status: "pending",
                            attempts: 0,
                            can_retry: false,
                            automatic_retry_scheduled: false,
                          }
                        : email,
                    ),
                  }
                : appointment,
            ),
          },
      );
    } catch (err) {
      if (err.response?.status === 401) setSession(null);
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
          <span className="section-label">{session.user.shop?.name || "Business"} · Schedule</span>
          <h1 className="section-title">Appointments</h1>
          <p>Manage bookings, reschedule visits, and keep your day running smoothly.</p>
        </div>
        {canManageBookings && (
          <Link className="btn btn-primary" to="/admin/appointments/new">
            Add appointment
          </Link>
        )}
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
            Appointments
            <select
              value={filters.view}
              onChange={(event) => setFilters({ ...filters, view: event.target.value })}
            >
              <option value="all">All appointments</option>
              <option value="today">Today</option>
              <option value="upcoming">Upcoming confirmed</option>
              <option value="past">Past confirmed starts</option>
            </select>
          </label>
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
          {canManageBookings && (
            <label>
              Email delivery
              <select
                name="email_status"
                value={filters.email_status}
                onChange={(event) => setFilters({ ...filters, email_status: event.target.value })}
              >
                <option value="">All email statuses</option>
                <option value="failed">Failed emails</option>
              </select>
            </label>
          )}
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
        {result?.data.length === 0 && (
          <p role="status">
            {query.email_status === "failed"
              ? "No appointments with failed emails match your filters."
              : "No appointments match your filters."}
          </p>
        )}
        <div className="admin-list">
          {result?.data.map((booking) => (
            <Appointment
              key={`${booking.id}-${booking.revision}`}
              booking={booking}
              barbers={barbers}
              busy={busy || loading}
              onUpdate={update}
              onRetryEmail={retryEmail}
              client={client}
              onPaymentSaved={() => {
                setMessage(
                  "Payment receipt updated. No money was charged or refunded by this website.",
                );
                setRefresh((value) => value + 1);
              }}
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
