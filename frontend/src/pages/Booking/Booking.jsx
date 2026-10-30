import { useEffect, useRef, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { ArrowLeft, ArrowRight, Check, CheckCircle2, Clock3, ShieldCheck } from "lucide-react";
import axios from "axios";
import { barbers as barberProfiles } from "../../data/barbers";
import { openGoogleCalendar, downloadAppleCalendar } from "../../utils/calendar";
import FormGuard from "../../components/FormGuard/FormGuard";
import { formError as errorText } from "../../utils/formError";
import "./Booking.scss";

const steps = ["Service", "Barber", "Date & time", "Your details"];
const emptyDetails = {
  booking_date: "",
  booking_time: "",
  customer_name: "",
  email: "",
  phone: "",
  notes: "",
  website: "",
};
const displayDate = (date) =>
  date
    ? new Intl.DateTimeFormat("en-ZA", {
        weekday: "short",
        day: "numeric",
        month: "long",
        year: "numeric",
      }).format(new Date(date.slice(0, 10) + "T12:00:00"))
    : "Choose a date";
const money = (price) =>
  new Intl.NumberFormat("en-ZA", {
    style: "currency",
    currency: "ZAR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price);

export default function Booking() {
  const [searchParams] = useSearchParams();
  const shopSlug = searchParams.get("shop") || "forge-and-fade";
  const [formData, setFormData] = useState({
    ...emptyDetails,
    service_id: searchParams.get("service") || "",
    barber_id: searchParams.get("barber") || "",
  });
  const [step, setStep] = useState(0);
  const [catalog, setCatalog] = useState(null);
  const [catalogError, setCatalogError] = useState("");
  const [catalogRefresh, setCatalogRefresh] = useState(0);
  const [availability, setAvailability] = useState({ key: "", times: [], error: "" });
  const [refresh, setRefresh] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [confirmedBooking, setConfirmedBooking] = useState(null);
  const heading = useRef(null);
  const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
  const selectedService = catalog?.services.find(
    (service) => service.id === Number(formData.service_id),
  );
  const selectedBarber =
    formData.barber_id === "any" && catalog?.barbers.length
      ? { id: "any", name: "Any available barber" }
      : catalog?.barbers.find((barber) => barber.id === Number(formData.barber_id));
  const availabilityKey = [
    shopSlug,
    formData.service_id,
    formData.barber_id,
    formData.booking_date,
    refresh,
  ].join("/");
  const readyForTimes = Boolean(
    catalog?.shop?.slug === shopSlug && selectedService && selectedBarber && formData.booking_date,
  );
  const checkingTimes = readyForTimes && availability.key !== availabilityKey;
  const times = availability.key === availabilityKey ? availability.times : [];
  const availabilityError = availability.key === availabilityKey ? availability.error : "";
  const validTime = !checkingTimes && !availabilityError && times.includes(formData.booking_time);
  const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: catalog?.timezone || "Africa/Johannesburg",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date());

  useEffect(() => {
    const controller = new AbortController();
    axios
      .get(apiUrl + "/bookings/catalog", {
        signal: controller.signal,
        timeout: 20000,
        params: { shop: shopSlug },
      })
      .then(({ data }) => setCatalog(data))
      .catch((err) => {
        if (!axios.isCancel(err))
          setCatalogError(
            "We couldn’t load services and barbers. Check your connection and retry.",
          );
      });
    return () => controller.abort();
  }, [apiUrl, catalogRefresh, shopSlug]);

  useEffect(() => {
    if (!readyForTimes) return;
    const controller = new AbortController();
    axios
      .get(apiUrl + "/bookings/availability", {
        signal: controller.signal,
        timeout: 20000,
        params: {
          shop: shopSlug,
          service_id: formData.service_id,
          barber_id: formData.barber_id,
          booking_date: formData.booking_date,
        },
      })
      .then(({ data }) => {
        if (!controller.signal.aborted)
          setAvailability({ key: availabilityKey, times: data.available_times || [], error: "" });
      })
      .catch((err) => {
        if (!axios.isCancel(err) && !controller.signal.aborted)
          setAvailability({ key: availabilityKey, times: [], error: errorText(err) });
      });
    return () => controller.abort();
  }, [
    apiUrl,
    readyForTimes,
    formData.service_id,
    formData.barber_id,
    formData.booking_date,
    availabilityKey,
    shopSlug,
  ]);

  const focusHeading = () =>
    requestAnimationFrame(() => {
      heading.current?.focus();
      heading.current?.scrollIntoView({ block: "start" });
    });
  const goToStep = (next) => {
    setStep(next);
    setError("");
    focusHeading();
  };
  const change = (name, value) => {
    setError("");
    setFormData((previous) => ({
      ...previous,
      [name]: value,
      ...(["service_id", "barber_id", "booking_date"].includes(name) ? { booking_time: "" } : {}),
    }));
  };
  const retryTimes = () => {
    change("booking_time", "");
    setRefresh((previous) => previous + 1);
  };
  const canContinue =
    step === 0 ? Boolean(selectedService) : step === 1 ? Boolean(selectedBarber) : validTime;

  const submit = async (event) => {
    event.preventDefault();
    if (loading) return;
    if (step < 3) {
      if (canContinue) goToStep(step + 1);
      return;
    }
    if (!selectedService || !selectedBarber || !validTime) {
      setStep(2);
      setError("Please choose an available time before confirming your booking.");
      focusHeading();
      return;
    }
    setLoading(true);
    setError("");
    try {
      const { data } = await axios.post(
        apiUrl + "/bookings",
        {
          ...formData,
          shop: shopSlug,
          service_id: selectedService.id,
          barber_id: selectedBarber.id,
          quoted_price: selectedService.price,
        },
        { timeout: 20000 },
      );
      setConfirmedBooking({
        ...data.booking,
        shop: data.shop,
        email_notification: data.email_notification,
      });
      focusHeading();
    } catch (err) {
      const fields = err.response?.data?.errors || {};
      if (
        err.response?.status === 409 ||
        (err.response?.status === 422 &&
          (fields.booking_time ||
            fields.booking_date ||
            fields.service_id ||
            fields.barber_id ||
            fields.quoted_price))
      ) {
        setFormData((previous) => ({ ...previous, booking_time: "" }));
        setRefresh((previous) => previous + 1);
        setStep(fields.service_id || fields.quoted_price ? 0 : fields.barber_id ? 1 : 2);
        if (fields.quoted_price || fields.service_id) {
          setCatalog(null);
          setCatalogRefresh((previous) => previous + 1);
        }
        focusHeading();
      }
      setError(
        err.response
          ? errorText(err)
          : "We couldn’t confirm whether your booking was saved. Contact the shop before trying again to avoid a duplicate appointment.",
      );
    } finally {
      setLoading(false);
    }
  };
  const startAgain = () => {
    setConfirmedBooking(null);
    setFormData({ ...emptyDetails, service_id: "", barber_id: "" });
    setRefresh((previous) => previous + 1);
    goToStep(0);
  };

  return (
    <main className="booking-page">
      <section className="booking-page__header">
        <div className="container">
          <span className="section-label">{catalog?.shop?.name || "Your next great cut"}</span>
          <h1 className="section-title">Reserve Your Chair.</h1>
          <p className="section-text">
            A few simple steps. Your service, your barber, your time. No account needed.
          </p>
        </div>
      </section>
      <section className="booking-page__content">
        <div className="container">
          {confirmedBooking ? (
            <div className="booking-confirmation">
              <CheckCircle2 className="booking-confirmation__icon" size={44} aria-hidden="true" />
              <span className="section-label">You’re booked</span>
              <h2 ref={heading} tabIndex={-1}>
                See you in the chair, {confirmedBooking.customer_name}.
              </h2>
              <p role="status">
                Your appointment is confirmed. Save these details or add the visit to your calendar.
              </p>
              <dl className="booking-confirmation__details">
                <div>
                  <dt>Booking reference</dt>
                  <dd className="booking-reference">{confirmedBooking.reference}</dd>
                </div>
                <div>
                  <dt>Service</dt>
                  <dd>
                    {confirmedBooking.service_name} · {confirmedBooking.duration} min
                  </dd>
                </div>
                <div>
                  <dt>Barber</dt>
                  <dd>{confirmedBooking.barber_name}</dd>
                </div>
                <div>
                  <dt>When</dt>
                  <dd>
                    {displayDate(confirmedBooking.booking_date)} at{" "}
                    {confirmedBooking.booking_time.slice(0, 5)}
                  </dd>
                </div>
                <div>
                  <dt>Service price</dt>
                  <dd>{money(confirmedBooking.price)} · Pay at the shop</dd>
                </div>
                <div>
                  <dt>Where</dt>
                  <dd>{confirmedBooking.shop?.address || "Contact your shop for directions"}</dd>
                </div>
              </dl>
              <p className="booking-hint">
                All appointment times are South Africa time. No online payment has been taken. Need
                to change your visit? Contact {confirmedBooking.shop?.name || "your shop"}
                {confirmedBooking.shop?.phone && (
                  <>
                    {" "}
                    at{" "}
                    <a href={`tel:${confirmedBooking.shop.phone.replace(/\s/g, "")}`}>
                      {confirmedBooking.shop.phone}
                    </a>
                  </>
                )}
                .
              </p>
              <p role="status">
                {confirmedBooking.email_notification === "pending"
                  ? "Your confirmation email is pending delivery. Keep your booking reference in case the email is delayed."
                  : "Email confirmation is not currently available. Keep your booking reference to manage your appointment."}
              </p>
              <div className="booking-confirmation__actions">
                <Link
                  className="btn btn-outline"
                  to={`/booking/manage?shop=${encodeURIComponent(shopSlug)}`}
                >
                  Manage booking
                </Link>
                <button
                  type="button"
                  className="btn btn-primary"
                  onClick={() => openGoogleCalendar(confirmedBooking)}
                >
                  Google Calendar
                </button>
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => downloadAppleCalendar(confirmedBooking)}
                >
                  Apple / Outlook Calendar
                </button>
              </div>
              <div className="booking-confirmation__footer">
                <Link to="/" className="btn btn-outline">
                  Back to home
                </Link>
                <button className="btn btn-outline" type="button" onClick={startAgain}>
                  Book another appointment
                </button>
              </div>
            </div>
          ) : (
            <>
              <nav aria-label="Booking progress" className="booking-progress">
                <ol>
                  {steps.map((label, index) => (
                    <li key={label}>
                      <button
                        type="button"
                        disabled={loading || index > step}
                        aria-current={step === index ? "step" : undefined}
                        onClick={() => goToStep(index)}
                      >
                        <span className="booking-progress__number">
                          {index < step ? <Check size={16} aria-hidden="true" /> : index + 1}
                        </span>
                        <span>{label}</span>
                      </button>
                    </li>
                  ))}
                </ol>
              </nav>
              <div className="booking-page__grid">
                <div className="booking-form-panel">
                  <div className="booking-step-heading">
                    <span className="section-label">Step {step + 1} of 4</span>
                    <h2 ref={heading} tabIndex={-1}>
                      {
                        [
                          "Choose your service",
                          "Choose your barber",
                          "Find your time",
                          "Make it yours",
                        ][step]
                      }
                    </h2>
                    <p>
                      {
                        [
                          "Pick the service that suits your style.",
                          "Choose who you’d like to see. Everyone brings their own craft.",
                          "Available starts fit your full service, opening hours, and breaks.",
                          "Review your visit and tell us how to reach you.",
                        ][step]
                      }
                    </p>
                  </div>
                  {catalogError && (
                    <div
                      className="booking-form__message booking-form__message--error"
                      role="alert"
                    >
                      <p>{catalogError}</p>
                      <button
                        type="button"
                        className="btn btn-outline"
                        onClick={() => {
                          setCatalogError("");
                          setCatalogRefresh((previous) => previous + 1);
                        }}
                      >
                        Retry services
                      </button>
                    </div>
                  )}
                  {!catalog && !catalogError && <p role="status">Loading services and barbers…</p>}
                  {catalog && (!catalog.services.length || !catalog.barbers.length) && (
                    <p role="status">
                      Online booking is temporarily unavailable because no active services or
                      barbers are listed. Please contact the shop.
                    </p>
                  )}
                  {error && (
                    <div
                      role="alert"
                      className="booking-form__message booking-form__message--error"
                    >
                      {error}
                    </div>
                  )}
                  {catalog && (
                    <form className="booking-form" onSubmit={submit}>
                      <fieldset disabled={loading}>
                        <FormGuard
                          value={formData.website}
                          onChange={(value) => change("website", value)}
                        />
                        {step === 0 && (
                          <fieldset className="booking-choices">
                            <legend className="booking-sr-only">Select a service</legend>
                            {catalog.services.map((service) => (
                              <label
                                className={
                                  "booking-choice" +
                                  (Number(formData.service_id) === service.id ? " is-selected" : "")
                                }
                                key={service.id}
                              >
                                <input
                                  type="radio"
                                  name="service_id"
                                  value={service.id}
                                  checked={Number(formData.service_id) === service.id}
                                  onChange={(event) =>
                                    change(event.target.name, event.target.value)
                                  }
                                  required
                                />
                                <span className="booking-choice__content">
                                  <span className="booking-choice__title">{service.name}</span>
                                  <span className="booking-choice__meta">
                                    <Clock3 size={15} aria-hidden="true" />
                                    {service.duration} minutes
                                  </span>
                                </span>
                                <strong>{money(service.price)}</strong>
                              </label>
                            ))}
                          </fieldset>
                        )}
                        {step === 1 && (
                          <fieldset className="booking-choices">
                            <legend className="booking-sr-only">Select a barber</legend>
                            <label
                              className={
                                "booking-choice booking-choice--barber" +
                                (formData.barber_id === "any" ? " is-selected" : "")
                              }
                            >
                              <input
                                type="radio"
                                name="barber_id"
                                value="any"
                                checked={formData.barber_id === "any"}
                                onChange={(event) => change(event.target.name, event.target.value)}
                                required
                              />
                              <span className="booking-choice__content">
                                <span className="booking-choice__title">Any available barber</span>
                                <span className="booking-choice__meta">
                                  Show all available times. Your barber is assigned when you
                                  confirm.
                                </span>
                              </span>
                            </label>
                            {catalog.barbers.map((barber) => {
                              const profile = barberProfiles.find(
                                (item) => item.id === barber.id && item.name === barber.name,
                              );
                              return (
                                <label
                                  className={
                                    "booking-choice booking-choice--barber" +
                                    (Number(formData.barber_id) === barber.id ? " is-selected" : "")
                                  }
                                  key={barber.id}
                                >
                                  <input
                                    type="radio"
                                    name="barber_id"
                                    value={barber.id}
                                    checked={Number(formData.barber_id) === barber.id}
                                    onChange={(event) =>
                                      change(event.target.name, event.target.value)
                                    }
                                    required
                                  />
                                  {profile && <img src={profile.image} alt="" />}
                                  <span className="booking-choice__content">
                                    <span className="booking-choice__title">{barber.name}</span>
                                    <span className="booking-choice__meta">
                                      {profile?.specialty || "Professional barber"}
                                    </span>
                                  </span>
                                </label>
                              );
                            })}
                          </fieldset>
                        )}
                        {step === 2 && (
                          <>
                            <div className="booking-form__group">
                              <label htmlFor="booking_date">Choose a date</label>
                              <input
                                type="date"
                                id="booking_date"
                                name="booking_date"
                                min={today}
                                value={formData.booking_date}
                                onChange={(event) => change(event.target.name, event.target.value)}
                                required
                              />
                              <p className="booking-hint">
                                Monday–Saturday, 08:00–18:00. Closed Sundays. Break: 13:00–14:00.
                                South Africa time.
                              </p>
                            </div>
                            {checkingTimes && <p role="status">Checking available times…</p>}
                            {availabilityError && (
                              <div
                                role="alert"
                                className="booking-form__message booking-form__message--error"
                              >
                                <p>{availabilityError}</p>
                                <button
                                  type="button"
                                  className="btn btn-outline"
                                  onClick={retryTimes}
                                >
                                  Retry availability
                                </button>
                              </div>
                            )}
                            {readyForTimes &&
                              !checkingTimes &&
                              !availabilityError &&
                              !times.length && (
                                <p className="booking-empty" role="status">
                                  No available times on this date. Try another date or go back to
                                  choose a different barber.
                                </p>
                              )}
                            {times.length > 0 && (
                              <fieldset className="booking-time-slots">
                                <legend>
                                  Available times{" "}
                                  <span>· {selectedService.duration}-minute service</span>
                                </legend>
                                {times.map((time) => (
                                  <label
                                    key={time}
                                    className={formData.booking_time === time ? "is-selected" : ""}
                                  >
                                    <input
                                      type="radio"
                                      name="booking_time"
                                      value={time}
                                      checked={formData.booking_time === time}
                                      onChange={(event) =>
                                        change(event.target.name, event.target.value)
                                      }
                                      required
                                    />
                                    <span>{time}</span>
                                  </label>
                                ))}
                              </fieldset>
                            )}
                            <p className="booking-hint">
                              A time is only reserved once you confirm your booking.
                            </p>
                          </>
                        )}
                        {step === 3 && (
                          <>
                            <div className="booking-review">
                              <CheckCircle2 size={22} aria-hidden="true" />
                              <div>
                                <strong>
                                  {selectedService?.name} with {selectedBarber?.name}
                                </strong>
                                <p>
                                  {displayDate(formData.booking_date)} · {formData.booking_time} ·{" "}
                                  {money(selectedService?.price)}
                                </p>
                              </div>
                            </div>
                            <div className="booking-form__group">
                              <label htmlFor="customer_name">Full name</label>
                              <input
                                id="customer_name"
                                name="customer_name"
                                autoComplete="name"
                                maxLength={100}
                                required
                                value={formData.customer_name}
                                onChange={(event) => change(event.target.name, event.target.value)}
                                placeholder="Your full name"
                              />
                            </div>
                            <div className="booking-form__row">
                              <div className="booking-form__group">
                                <label htmlFor="email">Email address</label>
                                <input
                                  id="email"
                                  name="email"
                                  type="email"
                                  autoComplete="email"
                                  maxLength={150}
                                  required
                                  value={formData.email}
                                  onChange={(event) =>
                                    change(event.target.name, event.target.value)
                                  }
                                  placeholder="you@example.com"
                                />
                              </div>
                              <div className="booking-form__group">
                                <label htmlFor="phone">Phone number</label>
                                <input
                                  id="phone"
                                  name="phone"
                                  type="tel"
                                  autoComplete="tel"
                                  maxLength={30}
                                  required
                                  value={formData.phone}
                                  onChange={(event) =>
                                    change(event.target.name, event.target.value)
                                  }
                                  placeholder="071 234 5678"
                                />
                              </div>
                            </div>
                            <div className="booking-form__group">
                              <label htmlFor="notes">
                                Anything your barber should know? <span>(optional)</span>
                              </label>
                              <textarea
                                id="notes"
                                name="notes"
                                rows={3}
                                maxLength={1000}
                                value={formData.notes}
                                onChange={(event) => change(event.target.name, event.target.value)}
                                placeholder="Style preferences or a note for your visit"
                              />
                            </div>
                            <p className="booking-hint">
                              <ShieldCheck size={16} aria-hidden="true" /> Your details are used to
                              manage your appointment. No account or online payment required.
                            </p>
                          </>
                        )}
                        <div className="booking-form__actions">
                          {step > 0 && (
                            <button
                              type="button"
                              className="btn btn-outline"
                              onClick={() => goToStep(step - 1)}
                            >
                              <ArrowLeft size={16} aria-hidden="true" />
                              Back
                            </button>
                          )}
                          <button
                            className="btn btn-primary"
                            type="submit"
                            disabled={loading || (step < 3 ? !canContinue : !validTime)}
                          >
                            {loading
                              ? "Confirming…"
                              : step === 3
                                ? "Confirm booking"
                                : "Continue to " + steps[step + 1].toLowerCase()}
                            {!loading && <ArrowRight size={16} aria-hidden="true" />}
                          </button>
                        </div>
                      </fieldset>
                    </form>
                  )}
                </div>
                <aside className="booking-info" aria-label="Your appointment summary">
                  <span className="section-label">Made for you</span>
                  <h2>Your appointment</h2>
                  <dl>
                    <div>
                      <dt>Service</dt>
                      <dd>
                        {selectedService ? (
                          <>
                            {selectedService.name}
                            <small>{selectedService.duration} minutes</small>
                          </>
                        ) : (
                          "Choose a service"
                        )}
                      </dd>
                    </div>
                    <div>
                      <dt>Barber</dt>
                      <dd>{selectedBarber?.name || "Choose a barber"}</dd>
                    </div>
                    <div>
                      <dt>Date & time</dt>
                      <dd>
                        {displayDate(formData.booking_date)}
                        {formData.booking_time && (
                          <small>{formData.booking_time} · South Africa time</small>
                        )}
                      </dd>
                    </div>
                    <div className="booking-info__total">
                      <dt>Service price</dt>
                      <dd>{selectedService ? money(selectedService.price) : "—"}</dd>
                    </div>
                  </dl>
                  <p>Pay at the shop. No online payment needed.</p>
                  <div className="booking-info__help">
                    <strong>Need a hand?</strong>
                    {catalog?.shop?.phone && (
                      <a href={`tel:${catalog.shop.phone.replace(/\s/g, "")}`}>
                        {catalog.shop.phone}
                      </a>
                    )}
                    <p>{catalog?.shop?.address || "Contact your shop for directions"}</p>
                  </div>
                </aside>
              </div>
            </>
          )}
        </div>
      </section>
    </main>
  );
}
