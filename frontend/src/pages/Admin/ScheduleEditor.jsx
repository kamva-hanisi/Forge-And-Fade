import { useState } from "react";
import "./ShopManagement.scss";

export default function ScheduleEditor({ data, busy, save, business = false }) {
  const [barber, setBarber] = useState(String(data.barbers?.[0]?.id || ""));
  const [draft, setDraft] = useState(business ? data.business_schedule : data.schedules[barber]);
  const [breaks, setBreaks] = useState(
    (draft?.breaks || []).map((range) => range.join("-")).join(", "),
  );
  const [closed, setClosed] = useState((draft?.closed_dates || []).join(", "));
  const choose = (id) => {
    const next = data.schedules[id];
    setBarber(id);
    setDraft(next);
    setBreaks((next.breaks || []).map((range) => range.join("-")).join(", "));
    setClosed((next.closed_dates || []).join(", "));
  };
  if (!draft) return <p>Add a barber profile first.</p>;
  const updateDay = (day, value) => setDraft({ ...draft, hours: { ...draft.hours, [day]: value } });
  return (
    <form
      className={business ? "staff-form" : "staff-card staff-form"}
      onSubmit={(event) => {
        event.preventDefault();
        save(business ? "/management/business-hours" : `/management/barbers/${barber}/schedule`, {
          hours: draft.hours,
          breaks: breaks.trim()
            ? breaks.split(",").map((range) =>
                range
                  .trim()
                  .split("-")
                  .map((time) => time.trim()),
              )
            : [],
          closed_dates: closed.trim() ? closed.split(",").map((date) => date.trim()) : [],
        });
      }}
    >
      {!business && <h2>Weekly availability</h2>}
      <p>
        {business
          ? "These hours, breaks and closed dates apply to every barber in your business."
          : "Barber hours follow the business’s opening hours and breaks."}{" "}
        Upcoming and in-progress appointments must remain valid. Times use {data.timezone}.
      </p>
      <fieldset className="staff-booking-fields" disabled={busy}>
        {!business && (
          <label>
            Barber profile
            <select value={barber} onChange={(event) => choose(event.target.value)}>
              {data.barbers.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                  {!item.active ? " (inactive)" : ""}
                </option>
              ))}
            </select>
          </label>
        )}
        {["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"].map(
          (name, index) => {
            const day = index + 1;
            const range = draft.hours[day];
            return (
              <div className="shop-management__day" key={day}>
                <label className="staff-checkbox">
                  <input
                    type="checkbox"
                    checked={Boolean(range)}
                    onChange={(event) =>
                      updateDay(day, event.target.checked ? ["08:00", "18:00"] : null)
                    }
                  />
                  {name}
                </label>
                {range && (
                  <>
                    <label>
                      {name} {business ? "opens" : "start"}
                      <input
                        type="time"
                        required
                        value={range[0]}
                        onChange={(event) => updateDay(day, [event.target.value, range[1]])}
                      />
                    </label>
                    <label>
                      {name} {business ? "closes" : "end"}
                      <input
                        type="time"
                        required
                        value={range[1]}
                        onChange={(event) => updateDay(day, [range[0], event.target.value])}
                      />
                    </label>
                  </>
                )}
              </div>
            );
          },
        )}
        <label>
          {business ? "Business-wide daily breaks" : "Additional daily breaks"}
          <input
            value={breaks}
            onChange={(event) => setBreaks(event.target.value)}
            placeholder="13:00-14:00, 16:00-16:15"
            maxLength={150}
          />
          <small>
            {business
              ? "Separate ranges with commas. These breaks apply every open day."
              : "Separate ranges with commas. Business-wide breaks still apply."}
          </small>
        </label>
        <label>
          {business ? "Business closed dates" : "Days off"}
          <input
            value={closed}
            onChange={(event) => setClosed(event.target.value)}
            placeholder="2026-12-25, 2027-01-01"
            maxLength={4500}
          />
          <small>Use YYYY-MM-DD dates separated by commas.</small>
        </label>
        <button className="btn btn-primary">
          {business ? "Save business hours" : "Save barber schedule"}
        </button>
      </fieldset>
    </form>
  );
}
