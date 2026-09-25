import { Link } from "react-router-dom";
import { barbers } from "../../data/barbers";

import "./BarbersSection.scss";

const BarbersSection = () => {
  return (
    <section className="barbers-section">
      <div className="container">

        <div className="barbers-section__header">
          <span className="section-label">
            Meet The Team
          </span>

          <h2 className="section-title">
            Your Barbers.
          </h2>

          <p className="section-text">
            Skilled barbers focused on clean cuts,
            professional service and attention to detail.
          </p>
        </div>

        <div className="barbers-section__grid">
          {barbers.map((barber) => (
            <article
              className="barber-card"
              key={barber.id}
            >
              <div className="barber-card__image">
                <img
                  src={barber.image}
                  alt={`${barber.name}, ${barber.role}`}
                  loading="lazy"
                />
              </div>

              <div className="barber-card__content">
                <h3>{barber.name}</h3>

                <span>{barber.role}</span>

                <p>{barber.specialty}</p>

                <Link
                  to={`/booking?barber=${barber.id}`}
                >
                  Book with {barber.name.split(" ")[0]}
                </Link>
              </div>
            </article>
          ))}
        </div>

      </div>
    </section>
  );
};

export default BarbersSection;
