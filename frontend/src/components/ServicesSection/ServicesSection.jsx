import { Link } from "react-router-dom";

import "./ServicesSection.scss";

import useShopServices from "../../hooks/useShopServices";

const ServicesSection = () => {
  const { services, loading, error, shop, retry } = useShopServices();
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
          {loading && <p role="status">Loading current services…</p>}
          {error && (
            <div role="alert">
              {error}
              <button type="button" className="btn btn-outline" onClick={retry}>
                Retry services
              </button>
            </div>
          )}
          {!loading && !error && services.length === 0 && (
            <p>No services are currently available.</p>
          )}
          {services.slice(0, 4).map((service) => (
            <div className="service-card" key={service.id}>
              <div>
                <h3>{service.name}</h3>

                <p>{service.description}</p>
              </div>

              <div className="service-card__bottom">
                <span>R{service.price}</span>

                <Link
                  to={`/booking?shop=${encodeURIComponent(shop)}&service=${service.id}`}
                  className="btn btn-outline"
                >
                  Book
                </Link>
              </div>
            </div>
          ))}
        </div>

        <div className="services-section__footer">
          <Link to="/services" className="btn btn-outline">
            View All Services
          </Link>
        </div>
      </div>
    </section>
  );
};

export default ServicesSection;
