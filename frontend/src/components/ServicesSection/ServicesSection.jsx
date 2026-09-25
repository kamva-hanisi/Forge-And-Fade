import { Link } from "react-router-dom";

import "./ServicesSection.scss";

import { services } from "../../data/services";

const ServicesSection = () => {
  return (
    <section className="services-section">
      <div className="container">

        <div className="services-section__header">
          <span className="section-label">Our Services</span>

          <h2 className="section-title">
            Simple Grooming.
            <br />
            Professional Results.
          </h2>

          <p className="section-text">
            Choose from our most popular barbering and grooming services.
          </p>
        </div>

        <div className="services-section__grid">
          {services.slice(0, 4).map((service) => (
            <div
              className="service-card"
              key={service.id}
            >
              <div>
                <h3>{service.name}</h3>

                <p>{service.description}</p>
              </div>

              <div className="service-card__bottom">
                <span>R{service.price}</span>

                <Link to={`/booking?service=${service.id}`}>
                  Book
                </Link>
              </div>
            </div>
          ))}
        </div>

        <div className="services-section__footer">
          <Link
            to="/services"
            className="btn btn-outline"
          >
            View All Services
          </Link>
        </div>

      </div>
    </section>
  );
};

export default ServicesSection;