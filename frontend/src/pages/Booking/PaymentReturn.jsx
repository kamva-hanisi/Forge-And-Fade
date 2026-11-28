import { useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import axios from "axios";
import { shopPath } from "../../utils/shopLinks";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
const messages = {
  pending_payment: "Your booking is on hold while we wait for Payfast’s test payment notification.",
  expired: "The appointment hold expired. Contact the business if you completed payment.",
  payment_review:
    "The test payment arrived after the appointment was released. Contact the business for help.",
  cancelled: "This appointment was cancelled. Contact the business if you completed payment.",
};

export default function PaymentReturn() {
  const [params] = useSearchParams();
  const reference = params.get("reference");
  const valid = /^PF-[A-Za-z0-9]{32}$/.test(reference || "");
  const [attempt, setAttempt] = useState(0);
  const [result, setResult] = useState(null);

  useEffect(() => {
    if (!valid) return;
    const controller = new AbortController();
    axios
      .get(`${apiUrl}/payfast/status/${reference}`, { signal: controller.signal, timeout: 12000 })
      .then(({ data }) => setResult({ reference, attempt, data }))
      .catch((error) => {
        if (!axios.isCancel(error)) setResult({ reference, attempt, error: true });
      });
    return () => controller.abort();
  }, [reference, attempt, valid]);

  const current = result?.reference === reference && result.attempt === attempt ? result : null;
  const booking = current?.data;
  return (
    <main className="container public-shop__loading">
      <h1>
        {booking?.status === "confirmed" ? "Appointment confirmed" : "Payfast payment status"}
      </h1>
      {!valid && <p role="alert">This payment link is incomplete.</p>}
      {valid && !current && <p role="status">Checking your payment status…</p>}
      {current?.error && (
        <p role="alert">Payment status is unavailable right now. Check again shortly.</p>
      )}
      {booking?.status === "confirmed" ? (
        <>
          <p>Your test payment was verified for {booking.shop.name}.</p>
          <p>Booking reference {booking.booking_reference}</p>
          <Link to={shopPath(booking.shop.slug, true)}>Manage booking</Link>
        </>
      ) : (
        booking && <p role="status">{messages[booking.status]}</p>
      )}
      {(current?.error || booking?.status === "pending_payment") && (
        <button
          className="btn btn-outline"
          type="button"
          onClick={() => setAttempt((value) => value + 1)}
        >
          Check again
        </button>
      )}
    </main>
  );
}
