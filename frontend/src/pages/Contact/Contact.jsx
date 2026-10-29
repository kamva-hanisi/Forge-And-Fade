import { useState } from "react";
import { Link } from "react-router-dom";
import axios from "axios";
import FormGuard from "../../components/FormGuard/FormGuard";
import { formError } from "../../utils/formError";

import "./Contact.scss";

const Contact = () => {
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    phone: "",
    message: "",
    website: "",
  });

  const [loading, setLoading] = useState(false);

  const [success, setSuccess] = useState("");
  const [error, setError] = useState("");

  const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

  const handleChange = (event) => {
    const { name, value } = event.target;

    setFormData((previous) => ({
      ...previous,
      [name]: value,
    }));
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    if (loading) return;

    setSuccess("");
    setError("");

    try {
      setLoading(true);

      const response = await axios.post(`${apiUrl}/contact`, formData, { timeout: 20000 });

      setSuccess(response.data.message);

      setFormData({
        name: "",
        email: "",
        phone: "",
        message: "",
        website: "",
      });
    } catch (err) {
      setError(formError(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <main className="contact-page">
      <section className="contact-page__header">
        <div className="container">
          <span className="section-label">Contact Us</span>

          <h1 className="section-title">Get In Touch.</h1>

          <p className="section-text">
            Have a question about our services or your appointment? Get in touch with Forge & Fade.
          </p>
        </div>
      </section>

      <section className="contact-page__content">
        <div className="container contact-page__grid">
          <div className="contact-info">
            <h2>Visit Forge & Fade</h2>

            <div className="contact-info__item">
              <span>Phone</span>

              <a href="tel:+27 65 136 0694">+27 65 136 0694</a>
            </div>

            <div className="contact-info__item">
              <span>Email</span>

              <a href="mailto:kamva@forgeandfade.co.za">kamva@forgeandfade.co.za</a>
            </div>

            <div className="contact-info__item">
              <span>Location</span>

              <p>Cape Town, South Africa</p>
            </div>

            <div className="contact-info__item">
              <span>Opening Hours</span>

              <p>
                Monday – Saturday
                <br />
                08:00 – 18:00
              </p>

              <p>Sunday: Closed</p>
            </div>

            <Link to="/booking" className="btn btn-primary">
              Book Appointment
            </Link>
          </div>

          <form className="contact-form" onSubmit={handleSubmit}>
            <FormGuard
              value={formData.website}
              onChange={(value) => setFormData((previous) => ({ ...previous, website: value }))}
            />
            <div className="contact-form__group">
              <label htmlFor="name">Full Name *</label>

              <input
                type="text"
                id="name"
                name="name"
                autoComplete="name"
                maxLength={100}
                disabled={loading}
                value={formData.name}
                onChange={handleChange}
                placeholder="Your full name"
                required
              />
            </div>

            <div className="contact-form__group">
              <label htmlFor="email">Email *</label>

              <input
                type="email"
                id="email"
                name="email"
                autoComplete="email"
                maxLength={150}
                disabled={loading}
                value={formData.email}
                onChange={handleChange}
                placeholder="you@example.com"
                required
              />
            </div>

            <div className="contact-form__group">
              <label htmlFor="phone">Phone</label>

              <input
                type="tel"
                id="phone"
                name="phone"
                autoComplete="tel"
                maxLength={30}
                disabled={loading}
                value={formData.phone}
                onChange={handleChange}
                placeholder="071 234 5678"
              />
            </div>

            <div className="contact-form__group">
              <label htmlFor="message">Message *</label>

              <textarea
                id="message"
                name="message"
                maxLength={1500}
                disabled={loading}
                rows="6"
                value={formData.message}
                onChange={handleChange}
                placeholder="How can we help?"
                required
              />
            </div>

            {success && (
              <div role="status" className="contact-form__success">
                {success}
              </div>
            )}

            {error && (
              <div role="alert" className="contact-form__error">
                {error}
              </div>
            )}

            <button type="submit" className="btn btn-primary" disabled={loading}>
              {loading ? "Sending..." : "Send Message"}
            </button>
          </form>
        </div>
      </section>
    </main>
  );
};

export default Contact;
