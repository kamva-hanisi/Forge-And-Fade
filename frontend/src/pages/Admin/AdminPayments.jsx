import { useEffect, useState } from "react";
import { Link, Navigate } from "react-router-dom";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import { adminHeaders } from "../../utils/adminHeaders";
import { rolesPath } from "../../utils/adminDashboard";
import { formError } from "../../utils/formError";
import "./AdminPayments.scss";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
const paymentLabels = {
  cash: "Cash",
  card: "External card terminal",
  bank_transfer: "Bank transfer",
  other: "Other manual payment",
};
const statusLabels = {
  received: "Received",
  voided: "Voided",
  verified: "Verified",
  review: "Needs review",
};

function PaymentHistory({ session, setSession }) {
  const [type, setType] = useState("");
  const [page, setPage] = useState(1);
  const [refresh, setRefresh] = useState(0);
  const [response, setResponse] = useState(null);
  const requestKey = [type, page, refresh].join(":");
  const current = response?.key === requestKey ? response : null;

  useEffect(() => {
    const controller = new AbortController();
    axios
      .get(`${apiUrl}/admin/payments`, {
        params: { page, type: type || undefined },
        headers: adminHeaders(session),
        timeout: 12000,
        signal: controller.signal,
      })
      .then(({ data }) => setResponse({ key: requestKey, data }))
      .catch((error) => {
        if (axios.isCancel(error)) return;
        if (error.response?.status === 401) setSession(null);
        else setResponse({ key: requestKey, error: formError(error) });
      });
    return () => controller.abort();
  }, [session, setSession, page, type, requestKey]);

  const data = current?.data;
  return (
    <div className="staff-card staff-payments">
      <div className="staff-card-heading">
        <div>
          <h1 className="section-title">Payments</h1>
          <p>{session.user.shop.name}</p>
        </div>
        {session.user.permissions.includes("payments.record") && (
          <Link className="btn btn-outline" to="/admin/appointments">
            Record payment on an appointment
          </Link>
        )}
      </div>
      <div className="staff-payments__controls">
        <label>
          Payment type
          <select
            value={type}
            onChange={(event) => {
              setType(event.target.value);
              setPage(1);
            }}
          >
            <option value="">All payments</option>
            <option value="cash">Cash only</option>
            <option value="manual">Cash and other manual receipts</option>
            <option value="payfast">Payfast test payments</option>
          </select>
        </label>
        <button className="btn btn-outline" type="button" onClick={() => setRefresh(refresh + 1)}>
          Refresh
        </button>
      </div>
      {!current && <p role="status">Loading payments…</p>}
      {current?.error && <p role="alert">{current.error}</p>}
      {data?.data.length === 0 && <p role="status">No payments match this selection.</p>}
      {data?.data.length > 0 && (
        <div
          className="staff-payments__table"
          role="region"
          aria-label="Payment history"
          tabIndex={0}
        >
          <table>
            <thead>
              <tr>
                <th scope="col">Date received</th>
                <th scope="col">Appointment</th>
                <th scope="col">Payment method</th>
                <th scope="col">Amount</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((payment) => (
                <tr key={`${payment.type}-${payment.entry_id}`}>
                  <td>
                    <time dateTime={payment.paid_on}>{payment.paid_on}</time>
                  </td>
                  <td>
                    <strong>{payment.customer_name}</strong>
                    <small>
                      {payment.service_name} · {payment.booking_reference}
                    </small>
                  </td>
                  <td>
                    {payment.type === "manual"
                      ? paymentLabels[payment.method]
                      : payment.type === "payfast"
                        ? "Payfast sandbox"
                        : "Previous online payment"}
                    {payment.transaction_id && <small>Transaction {payment.transaction_id}</small>}
                  </td>
                  <td>
                    {payment.currency} {Number(payment.amount).toFixed(2)}
                  </td>
                  <td>{statusLabels[payment.payment_status]}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {data?.last_page > 1 && (
        <nav className="admin-pagination" aria-label="Payment history pages">
          <button
            className="btn btn-outline"
            type="button"
            disabled={page === 1}
            onClick={() => setPage(page - 1)}
          >
            Previous
          </button>
          <span>
            Page {page} of {data.last_page}
          </span>
          <button
            className="btn btn-outline"
            type="button"
            disabled={page === data.last_page}
            onClick={() => setPage(page + 1)}
          >
            Next
          </button>
        </nav>
      )}
    </div>
  );
}

export default function AdminPayments() {
  const { session, setSession } = useAdmin();
  if (!session.user.shop || !session.user.permissions.includes("payments.read")) {
    return <Navigate to={rolesPath} replace />;
  }

  return <PaymentHistory key={session.user.shop.id} session={session} setSession={setSession} />;
}
