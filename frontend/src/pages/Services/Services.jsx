import { useState } from "react";
import { Link } from "react-router-dom";

import useShopServices from "../../hooks/useShopServices";
import { bookingLink } from "../../utils/shopLinks";
import { formatRand } from "../../utils/money";

import "./Services.scss";

const Services = () => {
  const { services, error, loading, shop, retry } = useShopServices();
  const [search, setSearch] = useState("");
  const [sort, setSort] = useState("listed");
  const term = search.trim().toLowerCase();
  const visible = services.filter((service) =>
    [service.name, service.description].some((text) => text?.toLowerCase().includes(term)),
  );
  if (sort === "price") visible.sort((a, b) => Number(a.price) - Number(b.price));
  if (sort === "duration") visible.sort((a, b) => a.duration - b.duration);
  return (
    <main className="services-page">
      <section className="services-page__header">
        <div className="container">
          <span className="section-label">Services & Pricing</span>

          <h1 className="section-title">Choose Your Service.</h1>

          <p className="section-text">
            Compare services, prices and appointment lengths. Pay at the business when you visit.
          </p>
        </div>
      </section>

      <section className="services-page__services">
        <div className="container">
          {services.length > 0 && (
            <div className="services-page__filters">
              <label>
                Find a service
                <input
                  type="search"
                  placeholder="Try skin fade or beard"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                />
              </label>
              <label>
                Sort by
                <select value={sort} onChange={(event) => setSort(event.target.value)}>
                  <option value="listed">As listed</option>
                  <option value="price">Lowest price</option>
                  <option value="duration">Shortest visit</option>
                </select>
              </label>
              <p role="status">
                {visible.length} {visible.length === 1 ? "service" : "services"}
              </p>
            </div>
          )}
          <div className="services-page__grid">
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
            {services.length > 0 && visible.length === 0 && (
              <div className="services-page__empty">
                <p>No services match your search.</p>
                <button className="btn btn-outline" type="button" onClick={() => setSearch("")}>
                  Clear search
                </button>
              </div>
            )}
            {visible.map((service) => (
              <article className="full-service-card" key={service.id}>
                <div className="full-service-card__content">
                  <h2>{service.name}</h2>

                  {service.description && <p>{service.description}</p>}

                  <span>{service.duration} minutes</span>
                </div>

                <div className="full-service-card__action">
                  <strong>{formatRand(service.price)}</strong>

                  <Link
                    to={bookingLink(shop, { service: service.id })}
                    className="btn btn-primary"
                    aria-label={`Book ${service.name}`}
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
            <h2>Ready for your next cut?</h2>

            <p>Choose your service, barber, date and preferred time online.</p>
          </div>

          <Link to={bookingLink(shop)} className="btn btn-primary">
            Book Appointment
          </Link>
        </div>
      </section>
    </main>
  );
};

export default Services;
