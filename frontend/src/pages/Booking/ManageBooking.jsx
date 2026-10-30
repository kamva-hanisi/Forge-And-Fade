import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import axios from "axios";
import { formError } from "../../utils/formError";
import "./ManageBooking.scss";
import { resolveShopSlug, shopPath } from "../../utils/shopLinks";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

export default function ManageBooking({ shop: requestedShop, dedicated = false }) {
  const [params] = useSearchParams();
  const shop = resolveShopSlug(requestedShop, params.get("shop"));
  const [reference, setReference] = useState("");
  const [email, setEmail] = useState("");
  const [result, setResult] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [confirm, setConfirm] = useState(false);

  async function submit(event, cancel = false) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      const { data } = await axios.post(
        `${apiUrl}/bookings/${cancel ? "cancel" : "lookup"}`,
        {
          shop,
          reference: reference.trim().toUpperCase(),
          email: email.trim(),
          ...(cancel ? { revision: result.booking.revision } : {}),
        },
        { timeout: 20000 },
      );
      setResult(data);
      setConfirm(false);
    } catch (failure) {
      setResult(null);
      setConfirm(false);
      setError(
        failure.response
          ? formError(failure)
          : "We could not confirm the result. Look up your booking again before retrying; contact the shop if the problem continues.",
      );
    } finally {
      setBusy(false);
    }
  }

  function change(setter, value) {
    setter(value);
    setResult(null);
    setConfirm(false);
    setError("");
  }

  return (
    <main className="manage-booking container">
      <span className="section-label">Your appointment</span>
      <h1 className="section-title">Manage your booking</h1>
      <p>Use the reference from your confirmation and the email used to book. No account needed.</p>
      <p>Keep your reference private: it and your email allow access to your appointment.</p>
      <form onSubmit={submit}>
        <fieldset disabled={busy}>
          <label htmlFor="booking-reference">Booking reference</label>
          <input
            id="booking-reference"
            value={reference}
            onChange={(event) => change(setReference, event.target.value)}
            required
            maxLength={19}
            placeholder="FF-XXXXXXXXXXXXXXXX"
            autoComplete="off"
          />
          <label htmlFor="booking-email">Booking email</label>
          <input
            id="booking-email"
            type="email"
            value={email}
            onChange={(event) => change(setEmail, event.target.value)}
            required
            maxLength={150}
            autoComplete="email"
          />
          <button className="btn btn-primary" disabled={busy}>
            {busy ? "Please wait…" : "Find booking"}
          </button>
        </fieldset>
      </form>
      {error && <p role="alert">{error}</p>}
      {result && (
        <section
          className="manage-booking__result"
          aria-labelledby="appointment-details"
          aria-live="polite"
        >
          <h2 id="appointment-details">
            {result.booking.status === "cancelled" ? "Booking cancelled" : "Your booking"}
          </h2>
          <dl>
            <dt>Reference</dt>
            <dd>{result.booking.reference}</dd>
            <dt>Service</dt>
            <dd>
              {result.booking.service_name} · {result.booking.duration} minutes
            </dd>
            <dt>Barber</dt>
            <dd>{result.booking.barber_name}</dd>
            <dt>When</dt>
            <dd>
              {result.booking.booking_date} at {result.booking.booking_time.slice(0, 5)} (
              {result.booking.timezone})
            </dd>
            <dt>Price</dt>
            <dd>
              {result.booking.currency} {result.booking.price}
            </dd>
            <dt>Status</dt>
            <dd>{result.booking.status}</dd>
          </dl>
          {result.can_cancel ? (
            <>
              <p>
                You can cancel before the appointment starts. To reschedule, contact the shop.
                Cancelling does not issue a payment refund.
              </p>
              {!confirm ? (
                <button
                  type="button"
                  className="btn btn-primary"
                  disabled={busy}
                  onClick={() => setConfirm(true)}
                >
                  Cancel appointment
                </button>
              ) : (
                <div>
                  <p>Are you sure? Your slot will become available to other customers.</p>
                  <button
                    type="button"
                    className="btn btn-primary"
                    disabled={busy}
                    onClick={(event) => submit(event, true)}
                  >
                    Yes, cancel appointment
                  </button>
                  <button
                    type="button"
                    className="btn"
                    disabled={busy}
                    onClick={() => setConfirm(false)}
                  >
                    Keep booking
                  </button>
                </div>
              )}
            </>
          ) : (
            <p>
              {result.booking.status === "cancelled"
                ? "Your slot has been released. Contact the shop about any payment questions."
                : "This appointment can no longer be cancelled online. Contact the shop."}
            </p>
          )}
        </section>
      )}
      <Link to={dedicated ? shopPath(shop) : `/booking?shop=${encodeURIComponent(shop)}`}>
        Book a new appointment
      </Link>
    </main>
  );
}
