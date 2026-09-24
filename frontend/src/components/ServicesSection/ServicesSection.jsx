import { Link } from "react-router-dom";

import "./ServicesSection.scss";

const services = [
  {
    id: 1,
    name: "Classic Haircut",
    description: "Clean, professional haircut finished with styling.",
    price: 220,
  },
  {
    id: 2,
    name: "Skin Fade",
    description: "Sharp skin fade with a clean and modern finish.",
    price: 250,
  },
  {
    id: 3,
    name: "Beard Trim",
    description: "Beard shaping, trimming and clean line-up.",
    price: 140,
  },
  {
    id: 4,
    name: "Haircut + Beard",
    description: "Complete haircut and beard grooming package.",
    price: 320,
  },
];

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
          {services.map((service) => (
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

                <Link to="/booking">
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