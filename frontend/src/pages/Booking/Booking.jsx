import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import axios from "axios";

import { services } from "../../data/services";
import { barbers } from "../../data/barbers";

import "./Booking.scss";

const timeSlots = [
  "08:00",
  "09:00",
  "10:00",
  "11:00",
  "12:00",
  "13:00",
  "14:00",
  "15:00",
  "16:00",
  "17:00",
];

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

  const [bookedTimes, setBookedTimes] = useState([]);
  const [loading, setLoading] = useState(false);
  const [checkingTimes, setCheckingTimes] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const apiUrl =
    import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

  const selectedService = services.find(
    (service) => service.id === Number(formData.service_id)
  );

  const selectedBarber = barbers.find(
    (barber) => barber.id === Number(formData.barber_id)
  );

  useEffect(() => {
    const getBookedTimes = async () => {
      if (!formData.barber_id || !formData.booking_date) {
        setBookedTimes([]);
        return;
      }

      try {
        setCheckingTimes(true);

        const response = await axios.get(
          `${apiUrl}/bookings/availability`,
          {
            params: {
              barber_id: formData.barber_id,
              booking_date: formData.booking_date,
            },
          }
        );

        setBookedTimes(response.data.booked_times || []);
      } catch (err) {
        console.error(err);
      } finally {
        setCheckingTimes(false);
      }
    };

    getBookedTimes();
  }, [formData.barber_id, formData.booking_date, apiUrl]);

  const handleChange = (event) => {
    const { name, value } = event.target;

    setFormData((previous) => ({
      ...previous,
      [name]: value,
      ...(name === "barber_id" || name === "booking_date"
        ? { booking_time: "" }
        : {}),
    }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    setMessage("");
    setError("");

    if (!selectedService || !selectedBarber) {
      setError("Please select a service and barber.");
      return;
    }

    try {
      setLoading(true);

      const payload = {
        ...formData,

        service_id: selectedService.id,
        service_name: selectedService.name,
        duration: selectedService.duration,

        barber_id: selectedBarber.id,
        barber_name: selectedBarber.name,
      };

      const response = await axios.post(
        `${apiUrl}/bookings`,
        payload
      );

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

      setBookedTimes([]);
    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Unable to complete booking. Please try again."
      );
    } finally {
      setLoading(false);
    }
  };

  const today = new Date().toISOString().split("T")[0];

  return (
    <main className="booking-page">

      <section className="booking-page__header">
        <div className="container">
          <span className="section-label">
            Book Appointment
          </span>

          <h1 className="section-title">
            Reserve Your Chair.
          </h1>

          <p className="section-text">
            Choose your service, barber, date and preferred time.
          </p>
        </div>
      </section>

      <section className="booking-page__content">
        <div className="container booking-page__grid">

          <form
            className="booking-form"
            onSubmit={handleSubmit}
          >
            <div className="booking-form__group">
              <label htmlFor="service_id">
                Service *
              </label>

              <select
                id="service_id"
                name="service_id"
                value={formData.service_id}
                onChange={handleChange}
                required
              >
                <option value="">
                  Select service
                </option>

                {services.map((service) => (
                  <option
                    value={service.id}
                    key={service.id}
                  >
                    {service.name} — R{service.price}
                  </option>
                ))}
              </select>
            </div>

            <div className="booking-form__group">
              <label htmlFor="barber_id">
                Barber *
              </label>

              <select
                id="barber_id"
                name="barber_id"
                value={formData.barber_id}
                onChange={handleChange}
                required
              >
                <option value="">
                  Select barber
                </option>

                {barbers.map((barber) => (
                  <option
                    value={barber.id}
                    key={barber.id}
                  >
                    {barber.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="booking-form__row">

              <div className="booking-form__group">
                <label htmlFor="booking_date">
                  Date *
                </label>

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
                <label htmlFor="booking_time">
                  Time *
                </label>

                <select
                  id="booking_time"
                  name="booking_time"
                  value={formData.booking_time}
                  onChange={handleChange}
                  disabled={
                    !formData.barber_id ||
                    !formData.booking_date ||
                    checkingTimes
                  }
                  required
                >
                  <option value="">
                    {checkingTimes
                      ? "Checking..."
                      : "Select time"}
                  </option>

                  {timeSlots.map((time) => {
                    const unavailable =
                      bookedTimes.includes(time);

                    return (
                      <option
                        value={time}
                        key={time}
                        disabled={unavailable}
                      >
                        {time}
                        {unavailable
                          ? " — Booked"
                          : ""}
                      </option>
                    );
                  })}
                </select>
              </div>

            </div>

            <div className="booking-form__group">
              <label htmlFor="customer_name">
                Full Name *
              </label>

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
                <label htmlFor="email">
                  Email *
                </label>

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
                <label htmlFor="phone">
                  Phone *
                </label>

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
              <label htmlFor="notes">
                Notes
              </label>

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
              <div className="booking-form__message booking-form__message--error">
                {error}
              </div>
            )}

            {message && (
              <div className="booking-form__message booking-form__message--success">
                {message}
              </div>
            )}

            <button
              type="submit"
              className="btn btn-primary booking-form__submit"
              disabled={loading}
            >
              {loading
                ? "Booking..."
                : "Confirm Booking"}
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
              </p>
            </div>

            <div>
              <strong>Sunday</strong>

              <p>Closed</p>
            </div>

            <div>
              <strong>Location</strong>

              <p>
                Cape Town, South Africa
              </p>
            </div>

            {selectedService && (
              <div className="booking-info__summary">

                <strong>
                  Your Selection
                </strong>

                <p>
                  {selectedService.name}
                </p>

                <p>
                  R{selectedService.price}
                  {" · "}
                  {selectedService.duration} min
                </p>

                {selectedBarber && (
                  <p>
                    Barber: {selectedBarber.name}
                  </p>
                )}

              </div>
            )}

          </aside>

        </div>
      </section>

    </main>
  );
};

export default Booking;