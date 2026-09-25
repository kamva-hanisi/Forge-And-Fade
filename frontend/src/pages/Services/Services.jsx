import { Link } from "react-router-dom";

import { services } from "../../data/services";

import "./Services.scss";

const Services = () => {
  return (
    <main className="services-page">

      <section className="services-page__header">
        <div className="container">

          <span className="section-label">
            Services & Pricing
          </span>

          <h1 className="section-title">
            Choose Your Service.
          </h1>

          <p className="section-text">
            Straightforward pricing and professional
            grooming services for every style.
          </p>

        </div>
      </section>

      <section className="services-page__services">
        <div className="container">

          <div className="services-page__grid">

            {services.map((service) => (
              <article
                className="full-service-card"
                key={service.id}
              >
                <div className="full-service-card__content">

                  <h2>{service.name}</h2>

                  <p>{service.description}</p>

                  <span>
                    {service.duration} minutes
                  </span>

                </div>

                <div className="full-service-card__action">

                  <strong>
                    R{service.price}
                  </strong>

                  <Link
                    to={`/booking?service=${service.id}`}
                    className="btn btn-primary"
                  >
                    Book
                  </Link>

                </div>
              </article>
            ))}

          </div>

        </div>
      </section>

      <section className="services-page__cta">
        <div className="container services-page__cta-content">

          <div>
            <h2>
              Ready for your next cut?
            </h2>

            <p>
              Choose your service, barber,
              date and preferred time online.
            </p>
          </div>

          <Link
            to="/booking"
            className="btn btn-primary"
          >
            Book Appointment
          </Link>

        </div>
      </section>

    </main>
  );
};

export default Services;