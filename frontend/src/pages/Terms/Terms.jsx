import "./Terms.scss";

const Terms = () => {
  return (
    <main className="terms-page">

      <section className="terms-page__header">
        <div className="container">

          <span className="section-label">
            Legal
          </span>

          <h1 className="section-title">
            Terms & Conditions
          </h1>

          <p className="section-text">
            Please read these terms before using the
            Forge & Fade website or booking an appointment.
          </p>

        </div>
      </section>

      <section className="terms-page__content">
        <div className="container">

          <div className="terms-page__document">

            <p className="terms-page__updated">
              Last updated: September 2026
            </p>

            <section>
              <h2>1. About Forge & Fade</h2>

              <p>
                Forge & Fade is a fictional barbershop website
                created to provide information about grooming
                services and allow customers to make appointments
                online.
              </p>
            </section>

            <section>
              <h2>2. Appointments</h2>

              <p>
                Customers are responsible for providing accurate
                information when booking an appointment, including
                their name, contact information, selected service,
                barber, date and time.
              </p>

              <p>
                An appointment is considered confirmed once the
                website displays a successful booking confirmation.
              </p>
            </section>

            <section>
              <h2>3. Appointment Availability</h2>

              <p>
                Appointment times are subject to availability.
                A selected time may become unavailable if another
                customer completes a booking before you.
              </p>
            </section>

            <section>
              <h2>4. Cancellations and Changes</h2>

              <p>
                Customers who need to cancel or change an
                appointment should contact Forge & Fade as early
                as possible using the contact information available
                on the website.
              </p>
            </section>

            <section>
              <h2>5. Late Arrivals</h2>

              <p>
                Customers should arrive on time for their
                appointment. A late arrival may result in a shorter
                appointment or the need to reschedule if there is
                not enough time to complete the selected service.
              </p>
            </section>

            <section>
              <h2>6. Prices</h2>

              <p>
                Service prices displayed on the website are shown
                in South African Rand. Forge & Fade may update its
                services or prices when necessary.
              </p>
            </section>

            <section>
              <h2>7. Calendar Events</h2>

              <p>
                Customers may add confirmed appointments to
                supported calendar applications. Customers should
                review the generated event details to make sure
                the date and time are correct.
              </p>
            </section>

            <section>
              <h2>8. Contact Information</h2>

              <p>
                Information submitted through the contact form is
                used to respond to customer enquiries. Customers
                should avoid submitting unnecessary sensitive
                information through the message field.
              </p>
            </section>

            <section>
              <h2>9. Website Use</h2>

              <p>
                Users should not intentionally interfere with the
                operation of the website, attempt to submit false
                bookings, or misuse the booking and contact
                features.
              </p>
            </section>

            <section>
              <h2>10. Changes to These Terms</h2>

              <p>
                These Terms & Conditions may be updated when the
                services or website functionality changes. The most
                recent version will be published on this page.
              </p>
            </section>

          </div>

        </div>
      </section>

    </main>
  );
};

export default Terms;