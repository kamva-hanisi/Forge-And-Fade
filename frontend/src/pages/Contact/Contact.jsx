import { useState } from "react";
import { Link } from "react-router-dom";
import axios from "axios";
import FormGuard from "../../components/FormGuard/FormGuard";
import { formError } from "../../utils/formError";
import { usePublicCatalog } from "../../hooks/useShopServices";
import ShopHours from "../../components/ShopHours/ShopHours";
import BusinessLink from "../../components/BusinessLink";
import { shopDirections, shopPath } from "../../utils/shopLinks";

import "./Contact.scss";

const Contact = () => {
  const {
    profile,
    hours,
    shop,
    loading: shopLoading,
    error: shopError,
    retry,
  } = usePublicCatalog();
  const directions = shopDirections(profile);
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

      const response = await axios.post(
        `${apiUrl}/contact`,
        { ...formData, shop },
        { timeout: 20000 },
      );

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
          <h1 className="section-title">Visit or contact the business</h1>

          <p className="section-text">
            Find the business’s hours and directions, manage an appointment, or send us a question.
          </p>
        </div>
      </section>

      <section className="contact-page__content">
        <div className="container contact-page__grid">
          <div className="contact-info">
            <h2>{profile?.name || "Business details"}</h2>
            {shopLoading && <p role="status">Loading business details…</p>}
            {shopError && (
              <div role="alert">
                <p>Business details are temporarily unavailable.</p>
                <button type="button" className="btn btn-outline" onClick={retry}>
                  Reload business details
                </button>
              </div>
            )}

            {profile?.phone && (
              <div className="contact-info__item">
                <span>Phone</span>
                <a href={`tel:${profile.phone.replace(/[^+\d]/g, "")}`}>{profile.phone}</a>
              </div>
            )}
            {(profile?.address || directions) && (
              <div className="contact-info__item">
                <span>Location</span>
                {profile?.address && <p>{profile.address}</p>}
                {directions && <a href={directions}>Get directions</a>}
              </div>
            )}
            {hours && (
              <div className="contact-info__item">
                <h3>Weekly opening hours</h3>
                <ShopHours hours={hours} className="contact-info__hours" />
                <p>Available appointment times account for breaks and closure dates.</p>
              </div>
            )}
            <div className="contact-info__actions">
              <Link to={shopPath(shop)} className="btn btn-primary">
                Book an appointment
              </Link>
              <Link to={shopPath(shop, true)} className="btn btn-outline">
                Manage a booking
              </Link>
            </div>
          </div>

          <form
            className="contact-form"
            onSubmit={handleSubmit}
            aria-labelledby="contact-form-title"
          >
            <h2 id="contact-form-title">Send a message</h2>
            <FormGuard
              value={formData.website}
              onChange={(value) => setFormData((previous) => ({ ...previous, website: value }))}
            />
            <div className="contact-form__fields">
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

              <div className="contact-form__group contact-form__group--wide">
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

              <div className="contact-form__group contact-form__group--wide contact-form__group--message">
                <label htmlFor="message">Message *</label>

                <textarea
                  id="message"
                  name="message"
                  maxLength={1500}
                  disabled={loading}
                  rows={5}
                  value={formData.message}
                  onChange={handleChange}
                  placeholder="How can we help?"
                  required
                />
              </div>
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

            <div className="contact-form__footer">
              <button type="submit" className="btn btn-primary" disabled={loading}>
                {loading ? "Sending..." : "Send Message"}
              </button>
              <p className="contact-form__privacy">
                Read our <BusinessLink to="/privacy">privacy notice</BusinessLink> before sharing
                personal details.
              </p>
            </div>
          </form>
        </div>
      </section>
      <section className="contact-page__faq">
        <div className="container">
          <h2 className="section-title">Booking questions</h2>
          <details>
            <summary>How do I cancel or change my appointment?</summary>
            <p>
              To cancel before your appointment, use{" "}
              <Link to={shopPath(shop, true)}>Manage a booking</Link> with your reference and email.
              Contact the business to change the time.
            </p>
          </details>
          <details>
            <summary>Do I need to pay online?</summary>
            <p>You can book online as a guest and pay at the business when you visit.</p>
          </details>
          <details>
            <summary>What if I’m unsure which service to book?</summary>
            <p>
              <BusinessLink to="/services">Compare services and prices</BusinessLink>. If you’re
              between options, tell us the look you want and what you’d like to keep or change.
            </p>
          </details>
        </div>
      </section>
    </main>
  );
};

export default Contact;
