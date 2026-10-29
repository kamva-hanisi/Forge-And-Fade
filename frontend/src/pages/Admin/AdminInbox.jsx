import { useEffect, useMemo, useState } from "react";
import { Navigate } from "react-router-dom";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import { formError } from "../../utils/formError";
import "./AdminInbox.scss";

const statuses = { new: "New", read: "Read", resolved: "Resolved" };

export default function AdminInbox() {
  const { session, setSession } = useAdmin();
  const allowed = session.user.permissions?.includes("messages.manage");
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState({ search: "", status: "", page: 1 });
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [refresh, setRefresh] = useState(0);
  const client = useMemo(
    () =>
      axios.create({
        baseURL: `${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/admin`,
        headers: { Authorization: `Bearer ${session.token}`, Accept: "application/json" },
        timeout: 20000,
      }),
    [session.token],
  );

  useEffect(() => {
    if (!allowed) return;
    const controller = new AbortController();
    client
      .get("/messages", { params: filters, signal: controller.signal })
      .then(({ data }) => {
        setResult(data);
        setError("");
      })
      .catch((err) => {
        if (axios.isCancel(err)) return;
        if (err.response?.status === 401) setSession(null);
        else {
          setResult(null);
          setError(formError(err));
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [allowed, client, filters, refresh, setSession]);

  const reload = () => {
    setLoading(true);
    setResult(null);
    setRefresh((value) => value + 1);
  };
  const changeFilters = (next) => {
    setLoading(true);
    setResult(null);
    setNotice("");
    setFilters(next);
  };
  const update = async (enquiry, status) => {
    if (saving) return;
    setSaving(true);
    setError("");
    setNotice("");
    try {
      const { data } = await client.patch(`/messages/${enquiry.id}`, {
        status,
        expected_status: enquiry.status,
      });
      setNotice(data.message);
      reload();
    } catch (err) {
      if (err.response?.status === 401) setSession(null);
      else setError(formError(err));
    } finally {
      setSaving(false);
    }
  };

  if (!allowed) return <Navigate to="/admin" replace />;
  return (
    <main className="staff-page staff-inbox">
      <span className="section-label">Customer enquiries</span>
      <h1 className="section-title">Contact inbox</h1>
      <p>
        Messages for {session.user.shop?.name}. Status changes stay internal; they do not send a
        reply.
      </p>
      <form
        className="staff-card staff-inbox__filters"
        onSubmit={(event) => {
          event.preventDefault();
          changeFilters({ ...filters, search: search.trim(), page: 1 });
        }}
      >
        <label htmlFor="inbox-search">
          Search enquiries
          <input
            id="inbox-search"
            type="search"
            maxLength={100}
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Name, email or message"
          />
        </label>
        <label htmlFor="inbox-status">
          Status
          <select
            id="inbox-status"
            value={filters.status}
            onChange={(event) => changeFilters({ ...filters, status: event.target.value, page: 1 })}
          >
            <option value="">All statuses</option>
            {Object.entries(statuses).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <button className="btn btn-primary" disabled={saving}>
          Search
        </button>
        <button
          type="button"
          className="btn btn-outline"
          onClick={reload}
          disabled={loading || saving}
        >
          Refresh
        </button>
      </form>
      {error && (
        <div className="admin-error" role="alert">
          {error}{" "}
          <button type="button" className="btn btn-outline" onClick={reload} disabled={loading}>
            Retry
          </button>
        </div>
      )}
      {notice && (
        <p className="admin-message" role="status">
          {notice}
        </p>
      )}
      {loading && <p role="status">Loading enquiries…</p>}
      {!loading && result && (
        <>
          <p className="staff-inbox__count" role="status">
            {result.total} matching {result.total === 1 ? "enquiry" : "enquiries"}
          </p>
          {result.data.length === 0 && (
            <section className="staff-card">
              <h2>No enquiries found</h2>
              <p>New contact messages will appear here. Try another search or status filter.</p>
            </section>
          )}
          <div className="staff-inbox__messages">
            {result.data.map((enquiry) => (
              <article
                key={enquiry.id}
                className="staff-card"
                aria-labelledby={`enquiry-${enquiry.id}`}
              >
                <div className="staff-inbox__heading">
                  <h2 id={`enquiry-${enquiry.id}`}>{enquiry.name}</h2>
                  <span className="staff-inbox__badge">
                    {statuses[enquiry.status] || enquiry.status}
                  </span>
                </div>
                <p className="staff-inbox__contact">
                  Email: {enquiry.email}
                  {enquiry.phone && (
                    <>
                      <br />
                      Phone: {enquiry.phone}
                    </>
                  )}
                </p>
                <p className="staff-inbox__date">
                  Received{" "}
                  {new Intl.DateTimeFormat("en-ZA", {
                    dateStyle: "medium",
                    timeStyle: "short",
                    timeZone: session.user.shop?.timezone || "Africa/Johannesburg",
                  }).format(new Date(enquiry.created_at))}
                </p>
                <p className="staff-inbox__body">{enquiry.message}</p>
                <div className="staff-inbox__actions">
                  {Object.entries(statuses)
                    .filter(([status]) => status !== enquiry.status)
                    .map(([status, label]) => (
                      <button
                        key={status}
                        type="button"
                        className="btn btn-outline"
                        disabled={saving}
                        onClick={() => update(enquiry, status)}
                      >
                        Mark as {label.toLowerCase()}
                      </button>
                    ))}
                </div>
              </article>
            ))}
          </div>
          <nav className="staff-inbox__pagination" aria-label="Inbox pages">
            <button
              type="button"
              className="btn btn-outline"
              disabled={result.current_page <= 1 || saving}
              onClick={() => changeFilters({ ...filters, page: result.current_page - 1 })}
            >
              Previous
            </button>
            <span>
              Page {result.current_page} of {result.last_page}
            </span>
            <button
              type="button"
              className="btn btn-outline"
              disabled={result.current_page >= result.last_page || saving}
              onClick={() => changeFilters({ ...filters, page: result.current_page + 1 })}
            >
              Next
            </button>
          </nav>
        </>
      )}
    </main>
  );
}
