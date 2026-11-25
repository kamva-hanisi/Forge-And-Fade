import { useEffect, useState } from "react";
import axios from "axios";

const slotDate = new Intl.DateTimeFormat("en-ZA", {
  weekday: "short",
  day: "numeric",
  month: "short",
});

export default function NextAvailable({ apiUrl, shop, service, barber, onSelect }) {
  const [result, setResult] = useState(null);
  useEffect(() => {
    const controller = new AbortController();
    axios
      .get(`${apiUrl}/bookings/next-available`, {
        params: { shop, service_id: service, barber_id: barber },
        signal: controller.signal,
        timeout: 10000,
      })
      .then(({ data }) => setResult(data.slots))
      .catch(() => {
        if (!controller.signal.aborted) setResult(false);
      });
    return () => controller.abort();
  }, [apiUrl, shop, service, barber]);

  return (
    <div className="booking-next">
      <h3>Next available</h3>
      {result?.length > 0 && barber === "any" && <p>With the first available barber.</p>}
      {result === null && <p role="status">Checking the next seven days…</p>}
      {result === false && <p>Couldn’t load suggestions. You can still choose a date.</p>}
      {result?.length === 0 && <p>No open times in the next seven days. Try a later date.</p>}
      {result?.length > 0 && (
        <ul>
          {result.map((slot) => (
            <li key={`${slot.booking_date} ${slot.booking_time}`}>
              <button type="button" onClick={() => onSelect(slot, barber)}>
                <span>{slotDate.format(new Date(slot.booking_date + "T12:00:00"))}</span>
                <strong>{slot.booking_time}</strong>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
