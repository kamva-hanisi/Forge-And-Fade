import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import axios from "axios";

import {
  openGoogleCalendar,
  downloadAppleCalendar,
} from "../../utils/calendar";

import "./Booking.scss";

const Booking = () => {
  const [searchParams] = useSearchParams();

  const serviceFromUrl = searchParams.get("service") || "";
  const barberFromUrl = searchParams.get("barber") || "";

  const [formData, setFormData] = useState({
    service_id: serviceFromUrl,
    barber_id: barberFromUrl,
    booking_date: "",
    booking_time: "",
    customer_name: "",
    email: "",
    phone: "",
    notes: "",
  });

  const [timeSlots, setTimeSlots] = useState([]);
  const [services, setServices] = useState([]);
  const [barbers, setBarbers] = useState([]);
  const [availabilityError, setAvailabilityError] = useState("");
  const [refresh, setRefresh] = useState(0);
  const [loading, setLoading] = useState(false);
  const [checkingTimes, setCheckingTimes] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const [confirmedBooking, setConfirmedBooking] = useState(null);

  const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

  const selectedService = services.find(
    (service) => service.id === Number(formData.service_id),
  );

  const selectedBarber = barbers.find(
    (barber) => barber.id === Number(formData.barber_id),
  );

  useEffect(() => {
    const controller = new AbortController();
    axios.get(`${apiUrl}/bookings/catalog`, { signal: controller.signal })
      .then(({ data }) => { setServices(data.services); setBarbers(data.barbers); })
      .catch((err) => { if (!axios.isCancel(err)) setError("Unable to load services and barbers. Please reload the page."); });
    return () => controller.abort();
  }, [apiUrl]);

  useEffect(() => {
    const controller = new AbortController();
    const getBookedTimes = async () => {
      setTimeSlots([]);
      setAvailabilityError("");
      if (!formData.service_id || !formData.barber_id || !formData.booking_date) {
        setCheckingTimes(false);
        return;
      }

      try {
        setCheckingTimes(true);

        const response = await axios.get(`${apiUrl}/bookings/availability`, {
          signal: controller.signal,
          params: {
            service_id: formData.service_id,
            barber_id: formData.barber_id,
            booking_date: formData.booking_date,
          },
        });

        setTimeSlots(response.data.available_times || []);
      } catch (err) {
        if (!axios.isCancel(err)) setAvailabilityError(err.response?.data?.message || "Unable to check availability. Please retry.");
      } finally {
        if (!controller.signal.aborted) setCheckingTimes(false);
      }
    };

    getBookedTimes();
    return () => controller.abort();
  }, [formData.service_id, formData.barber_id, formData.booking_date, apiUrl, refresh]);

  const handleChange = (event) => {
    const { name, value } = event.target;

    setError("");
    setMessage("");
    setConfirmedBooking(null);

    setFormData((previous) => ({
      ...previous,
      [name]: value,
      ...(name === "service_id" || name === "barber_id" || name === "booking_date"
        ? { booking_time: "" }
        : {}),
    }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    setMessage("");
    setError("");
    setConfirmedBooking(null);

    if (!selectedService || !selectedBarber) {
      setError("Please select a service and barber.");
      return;
    }

    if (checkingTimes || availabilityError || !timeSlots.includes(formData.booking_time)) {
      setError("Please choose an available time.");
      return;
    }

    try {
      setLoading(true);

      const response = await axios.post(`${apiUrl}/bookings`, {
        ...formData,
        service_id: selectedService.id,
        barber_id: selectedBarber.id,
      });

      setConfirmedBooking(response.data.booking);
      setMessage(response.data.message);

      setFormData({
        service_id: "",
        barber_id: "",
        booking_date: "",
        booking_time: "",
        customer_name: "",
        email: "",
        phone: "",
        notes: "",
      });

      setTimeSlots([]);
    } catch (err) {
      if (err.response?.status === 409 || err.response?.status === 422) {
        setFormData((previous) => ({ ...previous, booking_time: "" }));
        setRefresh((previous) => previous + 1);
      }
      setError(
        Object.values(err.response?.data?.errors || {}).flat().join(" ") || err.response?.data?.message ||
          "Unable to complete booking. Please try again.",
      );
    } finally {
      setLoading(false);
    }
  };

  const today = new Intl.DateTimeFormat("en-CA", { timeZone: "Africa/Johannesburg", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());

  return (
    <main className="booking-page">
      <section className="booking-page__header">
        <div className="container">
          <span className="section-label">Book Appointment</span>

          <h1 className="section-title">Reserve Your Chair.</h1>

          <p className="section-text">
            Choose your service, barber, date and preferred time.
          </p>
        </div>
      </section>

      <section className="booking-page__content">
        <div className="container booking-page__grid">
          <form className="booking-form" onSubmit={handleSubmit}>
            <div className="booking-form__group">
              <label htmlFor="service_id">Service *</label>

              <select
                id="service_id"
                name="service_id"
                value={formData.service_id}
                onChange={handleChange}
                required
              >
                <option value="">Select service</option>

                {services.map((service) => (
                  <option value={service.id} key={service.id}>
                    {service.name} — R{service.price}
                  </option>
                ))}
              </select>
            </div>

            <div className="booking-form__group">
              <label htmlFor="barber_id">Barber *</label>

              <select
                id="barber_id"
                name="barber_id"
                value={formData.barber_id}
                onChange={handleChange}
                required
              >
                <option value="">Select barber</option>

                {barbers.map((barber) => (
                  <option value={barber.id} key={barber.id}>
                    {barber.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="booking-form__row">
              <div className="booking-form__group">
                <label htmlFor="booking_date">Date *</label>

                <input
                  type="date"
                  id="booking_date"
                  name="booking_date"
                  min={today}
                  value={formData.booking_date}
                  onChange={handleChange}
                  required
                />
              </div>

              <div className="booking-form__group">
                <label htmlFor="booking_time">Time *</label>

                <select
                  id="booking_time"
                  name="booking_time"
                  value={formData.booking_time}
                  onChange={handleChange}
                  disabled={
                    !formData.service_id || availabilityError ||
                    !formData.barber_id ||
                    !formData.booking_date ||
                    checkingTimes
                  }
                  required
                >
                  <option value="">
                    {checkingTimes ? "Checking..." : "Select time"}
                  </option>

                  {timeSlots.map((time) => <option value={time} key={time}>{time}</option>)}
                </select>
                {availabilityError && <div role="alert" className="booking-form__message booking-form__message--error"><p>{availabilityError}</p><button type="button" className="btn btn-outline" onClick={() => setRefresh((previous) => previous + 1)}>Retry</button></div>}
                {!checkingTimes && !availabilityError && formData.service_id && formData.barber_id && formData.booking_date && timeSlots.length === 0 && <p role="status">No available times for this selection.</p>}
              </div>
            </div>

            <div className="booking-form__group">
              <label htmlFor="customer_name">Full Name *</label>

              <input
                type="text"
                id="customer_name"
                name="customer_name"
                value={formData.customer_name}
                onChange={handleChange}
                placeholder="Your full name"
                required
              />
            </div>

            <div className="booking-form__row">
              <div className="booking-form__group">
                <label htmlFor="email">Email *</label>

                <input
                  type="email"
                  id="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  placeholder="you@example.com"
                  required
                />
              </div>

              <div className="booking-form__group">
                <label htmlFor="phone">Phone *</label>

                <input
                  type="tel"
                  id="phone"
                  name="phone"
                  value={formData.phone}
                  onChange={handleChange}
                  placeholder="071 234 5678"
                  required
                />
              </div>
            </div>

            <div className="booking-form__group">
              <label htmlFor="notes">Notes</label>

              <textarea
                id="notes"
                name="notes"
                rows="4"
                value={formData.notes}
                onChange={handleChange}
                placeholder="Anything your barber should know?"
              />
            </div>

            {error && (
              <div role="alert" className="booking-form__message booking-form__message--error">
                {error}
              </div>
            )}

            {message && (
              <div role="status" className="booking-form__message booking-form__message--success">
                {message}
              </div>
            )}

            {confirmedBooking && (
              <div className="calendar-options">
                <h3>Appointment Confirmed</h3>

                <p>
                  Add your appointment to your calendar so you don't forget your
                  visit.
                </p>

                <div className="calendar-options__buttons">
                  <button
                    type="button"
                    className="btn btn-primary"
                    onClick={() => openGoogleCalendar(confirmedBooking)}
                  >
                    Add to Google Calendar
                  </button>

                  <button
                    type="button"
                    className="btn btn-outline"
                    onClick={() => downloadAppleCalendar(confirmedBooking)}
                  >
                    Add to Apple Calendar
                  </button>
                </div>
              </div>
            )}

            <button
              type="submit"
              className="btn btn-primary booking-form__submit"
              disabled={loading || checkingTimes || !!availabilityError || !timeSlots.includes(formData.booking_time)}
            >
              {loading ? "Booking..." : "Confirm Booking"}
            </button>
          </form>

          <aside className="booking-info">
            <h2>Forge & Fade</h2>

            <div>
              <strong>Opening Hours</strong>

              <p>
                Monday – Saturday
                <br />
                08:00 – 18:00
                <br />
                Break: 13:00 – 14:00 (South Africa time)
              </p>
            </div>

            <div>
              <strong>Sunday</strong>

              <p>Closed</p>
            </div>

            <div>
              <strong>Location</strong>

              <p>Cape Town, South Africa</p>
            </div>

            {selectedService && (
              <div className="booking-info__summary">
                <strong>Your Selection</strong>

                <p>{selectedService.name}</p>

                <p>
                  R{selectedService.price}
                  {" · "}
                  {selectedService.duration} min
                </p>

                {selectedBarber && <p>Barber: {selectedBarber.name}</p>}
              </div>
            )}
          </aside>
        </div>
      </section>
    </main>
  );
};

export default Booking;
