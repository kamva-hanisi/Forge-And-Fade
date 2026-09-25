import { Link } from "react-router-dom";

import "./Contact.scss";

const Contact = () => {
  return (
    <main className="contact-page">

      <section className="contact-page__header">
        <div className="container">

          <span className="section-label">
            Contact Us
          </span>

          <h1 className="section-title">
            Get In Touch.
          </h1>

          <p className="section-text">
            Have a question about our services or your appointment?
            Get in touch with Forge & Fade.
          </p>

        </div>
      </section>

      <section className="contact-page__content">
        <div className="container contact-page__grid">

          {/* CONTACT INFORMATION */}
          <div className="contact-info">

            <h2>
              Visit Forge & Fade
            </h2>

            <div className="contact-info__item">
              <span>Phone</span>

              <a href="tel:+27 65 136 0694">
                +27 65 136 0694
              </a>
            </div>

            <div className="contact-info__item">
              <span>Email</span>

              <a href="mailto:kamva@forgeandfade.co.za">
                kamva@forgeandfade.co.za
              </a>
            </div>

            <div className="contact-info__item">
              <span>Location</span>

              <p>
                Cape Town, South Africa
              </p>
            </div>

            <div className="contact-info__item">
              <span>Opening Hours</span>

              <p>
                Monday – Saturday
                <br />
                08:00 – 18:00
              </p>

              <p>
                Sunday: Closed
              </p>
            </div>

            <Link
              to="/booking"
              className="btn btn-primary"
            >
              Book Appointment
            </Link>

          </div>

          {/* CONTACT FORM */}
          <form className="contact-form">

            <div className="contact-form__group">
              <label htmlFor="name">
                Full Name
              </label>

              <input
                type="text"
                id="name"
                name="name"
                placeholder="Your full name"
                required
              />
            </div>

            <div className="contact-form__group">
              <label htmlFor="email">
                Email
              </label>

              <input
                type="email"
                id="email"
                name="email"
                placeholder="you@example.com"
                required
              />
            </div>

            <div className="contact-form__group">
              <label htmlFor="phone">
                Phone
              </label>

              <input
                type="tel"
                id="phone"
                name="phone"
                placeholder="071 234 5678"
              />
            </div>

            <div className="contact-form__group">
              <label htmlFor="message">
                Message
              </label>

              <textarea
                id="message"
                name="message"
                rows="6"
                placeholder="How can we help?"
                required
              />
            </div>

            <button
              type="submit"
              className="btn btn-primary"
            >
              Send Message
            </button>

          </form>

        </div>
      </section>

    </main>
  );
};

export default Contact;