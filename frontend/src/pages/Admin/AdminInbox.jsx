import { useEffect, useMemo, useState } from "react";
import { Navigate, useSearchParams } from "react-router-dom";
import axios from "axios";
import { adminHeaders } from "../../utils/adminHeaders";
import { useAdmin } from "../../context/AdminContext";
import { formError } from "../../utils/formError";
import "./AdminInbox.scss";

const statuses = { new: "New", read: "Read", resolved: "Resolved" };
const replyStatuses = { pending: "Queued", sent: "Sent", failed: "Failed" };

function ReplyEditor({ enquiry, saving, onSend, onClose }) {
  const [body, setBody] = useState("");
  const [requestId] = useState(() => crypto.randomUUID());

  return (
    <form
      className="staff-inbox__reply-form"
      onSubmit={(event) => {
        event.preventDefault();
        if (body.trim()) onSend(enquiry, { body: body.trim(), request_id: requestId });
      }}
    >
      <p>To {enquiry.email}</p>
      <label>
        Your reply
        <textarea
          autoFocus
          rows={5}
          maxLength={3000}
          value={body}
          onChange={(event) => setBody(event.target.value)}
          required
          disabled={saving}
        />
      </label>
      <div className="staff-inbox__actions">
        <button className="btn btn-primary" disabled={saving || !body.trim()}>
          {saving ? "Queuing…" : "Send reply"}
        </button>
        <button className="btn btn-outline" type="button" disabled={saving} onClick={onClose}>
          Cancel
        </button>
      </div>
    </form>
  );
}

export default function AdminInbox() {
  const { session, setSession } = useAdmin();
  const allowed = session.user.permissions?.includes("messages.manage");
  const [params] = useSearchParams();
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState(() => ({
    search: "",
    status: params.get("status") === "new" ? "new" : "",
    page: 1,
  }));
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [refresh, setRefresh] = useState(0);
  const [replyingTo, setReplyingTo] = useState(null);
  const hasPendingReply = result?.data.some(
    (enquiry) => enquiry.latest_reply?.status === "pending",
  );
  const inboxTime = new Intl.DateTimeFormat("en-ZA", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: session.user.shop?.timezone || "Africa/Johannesburg",
  });
  const client = useMemo(
    () =>
      axios.create({
        baseURL: `${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/admin`,
        headers: adminHeaders(session),
        timeout: 20000,
      }),
    [session],
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

  useEffect(() => {
    if (!allowed || !hasPendingReply || saving || loading) return;
    const controller = new AbortController();
    let checking = false;
    const timer = window.setInterval(async () => {
      if (checking || document.hidden) return;
      checking = true;
      try {
        const { data } = await client.get("/messages", {
          params: filters,
          signal: controller.signal,
        });
        if (!controller.signal.aborted) setResult(data);
      } catch (err) {
        if (controller.signal.aborted) return;
        if (err.response?.status === 401) setSession(null);
        if (err.response?.status === 403) {
          setResult(null);
          setError(formError(err));
        }
      } finally {
        checking = false;
      }
    }, 5000);
    return () => {
      controller.abort();
      window.clearInterval(timer);
    };
  }, [allowed, client, filters, hasPendingReply, loading, saving, setSession]);

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

  const sendReply = async (enquiry, payload) => {
    if (saving) return;
    setSaving(true);
    setError("");
    setNotice("");
    try {
      const { data } = await client.post(`/messages/${enquiry.id}/reply`, payload);
      setResult(
        (current) =>
          current && {
            ...current,
            data: current.data.map((message) =>
              message.id === enquiry.id ? { ...message, latest_reply: data.reply } : message,
            ),
          },
      );
      setNotice(data.message);
      setReplyingTo(null);
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
      <p>Messages for {session.user.shop?.name}. Use Reply to email the sender.</p>
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
                  Received {inboxTime.format(new Date(enquiry.created_at))}
                </p>
                <p className="staff-inbox__body">{enquiry.message}</p>
                {enquiry.latest_reply && (
                  <div className="staff-inbox__reply">
                    <p>
                      Latest reply{" "}
                      <span
                        className={`staff-inbox__badge staff-inbox__badge--${enquiry.latest_reply.status}`}
                        aria-live="polite"
                      >
                        {replyStatuses[enquiry.latest_reply.status]}
                      </span>
                    </p>
                    {enquiry.latest_reply.sent_at && (
                      <time className="staff-inbox__date" dateTime={enquiry.latest_reply.sent_at}>
                        {inboxTime.format(new Date(enquiry.latest_reply.sent_at))}
                      </time>
                    )}
                    <details>
                      <summary>View reply</summary>
                      <p className="staff-inbox__body">{enquiry.latest_reply.body}</p>
                    </details>
                  </div>
                )}
                <div className="staff-inbox__actions">
                  {replyingTo !== enquiry.id && (
                    <button
                      type="button"
                      className="btn btn-primary"
                      disabled={saving || enquiry.latest_reply?.status === "pending"}
                      onClick={() => setReplyingTo(enquiry.id)}
                    >
                      Reply
                    </button>
                  )}
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
                {replyingTo === enquiry.id && (
                  <ReplyEditor
                    enquiry={enquiry}
                    saving={saving}
                    onSend={sendReply}
                    onClose={() => setReplyingTo(null)}
                  />
                )}
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
