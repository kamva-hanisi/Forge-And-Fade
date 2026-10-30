import { useState } from "react";
import { useAdmin } from "../../context/AdminContext";
import { formError } from "../../utils/formError";

export default function PaymentPanel({ booking, client, onSaved }) {
  const { session, setSession } = useAdmin();
  const [open, setOpen] = useState(false);
  const [amount, setAmount] = useState(booking.price || "");
  const [method, setMethod] = useState("cash");
  const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: booking.timezone || "Africa/Johannesburg",
  }).format(new Date());
  const [date, setDate] = useState(today);
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  if (!session.user.permissions?.includes("reports.read")) return null;
  const payment = booking.active_payment;
  const submit = async (event) => {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      if (payment) await client.post(`/admin/payments/${payment.id}/void`, { reason });
      else
        await client.post(`/admin/bookings/${booking.id}/payments`, {
          amount,
          method,
          received_on: date,
        });
      setOpen(false);
      onSaved();
    } catch (err) {
      if (err.response?.status === 401) setSession(null);
      else setError(formError(err));
    } finally {
      setBusy(false);
    }
  };
  return (
    <section className="staff-payment">
      <h3>Payment receipt</h3>
      {payment ? (
        <p>
          Recorded: {payment.currency} {payment.amount} · {payment.method.replace("_", " ")} ·{" "}
          {payment.received_on.slice(0, 10)}
        </p>
      ) : (
        <p>No payment receipt recorded. A confirmed booking does not prove payment.</p>
      )}
      {error && (
        <p className="admin-error" role="alert">
          {error}
        </p>
      )}
      {((!payment && booking.status !== "cancelled") ||
        (payment && session.user.permissions?.includes("shop.manage"))) && (
        <button
          type="button"
          className="btn btn-outline"
          disabled={busy}
          onClick={() => setOpen(!open)}
        >
          {open
            ? "Close payment form"
            : payment
              ? "Void incorrect receipt"
              : "Record payment received"}
        </button>
      )}
      {open && (
        <form className="staff-form" onSubmit={submit}>
          <fieldset className="staff-booking-fields" disabled={busy}>
            {payment ? (
              <>
                <p>
                  This corrects reporting only, not a refund. The original receipt and audited void
                  remain in the database.
                </p>
                <label>
                  Reason for voiding
                  <textarea
                    required
                    maxLength={500}
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                  />
                </label>
              </>
            ) : (
              <>
                <p>
                  Only record money already received outside this website. This does not charge the
                  customer.
                </p>
                <label>
                  Amount received ({booking.currency})
                  <input
                    type="number"
                    required
                    min="0.01"
                    max="99999999.99"
                    step="0.01"
                    value={amount}
                    onChange={(event) => setAmount(event.target.value)}
                  />
                </label>
                <label>
                  Payment method
                  <select value={method} onChange={(event) => setMethod(event.target.value)}>
                    <option value="cash">Cash</option>
                    <option value="card">Card (external terminal)</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="other">Other</option>
                  </select>
                </label>
                <label>
                  Received on
                  <input
                    type="date"
                    required
                    min="2000-01-01"
                    max={today}
                    value={date}
                    onChange={(event) => setDate(event.target.value)}
                  />
                </label>
              </>
            )}
            <button className="btn btn-primary">
              {busy
                ? "Saving…"
                : payment
                  ? "Confirm void — no refund"
                  : "Confirm payment already received"}
            </button>
          </fieldset>
        </form>
      )}
    </section>
  );
}
