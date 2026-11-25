import { Link } from "react-router-dom";
import "./Terms.scss";

export default function Privacy() {
  return (
    <main className="terms-page">
      <section className="terms-page__header">
        <div className="container">
          <h1 className="section-title">Booking privacy</h1>
          <p className="section-text">
            How Forge &amp; Fade uses the details you share when booking or contacting a shop.
          </p>
        </div>
      </section>
      <section className="terms-page__content">
        <div className="container terms-page__document">
          <section>
            <h2>Information you provide</h2>
            <p>
              We collect your name, email address, phone number, appointment details and any notes
              you enter. The contact form also collects the message you send. Please keep sensitive
              personal information out of notes and messages.
            </p>
          </section>
          <section>
            <h2>How your details are used</h2>
            <p>
              Your selected shop uses these details to manage your appointment or respond to your
              enquiry. The booking system uses your email address for appointment notifications when
              email delivery is enabled. Keep your booking reference private because it can be used
              with your email address to look up and cancel your appointment.
            </p>
          </section>
          <section>
            <h2>Questions and requests</h2>
            <p>
              Contact the shop shown on your booking page if you have a privacy question or want to
              request access, a correction or deletion. For questions about Forge &amp; Fade, use
              the <Link to="/contact">contact form</Link>.
            </p>
          </section>
        </div>
      </section>
    </main>
  );
}
