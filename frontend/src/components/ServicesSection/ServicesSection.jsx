import { Link } from "react-router-dom";

import "./ServicesSection.scss";

import { bookingLink } from "../../utils/shopLinks";
import { formatRand } from "../../utils/money";
import BusinessLink from "../BusinessLink";

const ServicesSection = ({ catalog }) => {
  const { services, loading, error, shop, retry } = catalog;
  return (
    <section className="services-section">
      <div className="container">
        <div className="services-section__header">
          <h2 className="section-title">Services and prices</h2>
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

                {service.description && <p>{service.description}</p>}
              </div>

              <div className="service-card__bottom">
                <div>
                  <span>{formatRand(service.price)}</span>
                  <small>{service.duration} min</small>
                </div>

                <Link to={bookingLink(shop, { service: service.id })} className="btn btn-outline">
                  Book
                </Link>
              </div>
            </div>
          ))}
        </div>

        <div className="services-section__footer">
          <BusinessLink to="/services" className="btn btn-outline">
            View All Services
          </BusinessLink>
        </div>
      </div>
    </section>
  );
};

export default ServicesSection;
