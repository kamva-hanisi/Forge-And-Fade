import { Link } from "react-router-dom";

import heroBarber from "../../assets/hero-barber.jpg";

import "./Hero.scss";

const Hero = () => {
  return (
    <section className="hero">
      <div className="container hero__container">

        <div className="hero__content">

          <span className="section-label">
            Premium Barber Shop
          </span>

          <h1>
            Look Sharp.
            <br />
            <span>Feel Confident.</span>
          </h1>

          <p>
            Professional haircuts, clean fades and beard grooming
            delivered with care and attention to detail.
          </p>

          <div className="hero__buttons">

            <Link
              to="/booking"
              className="btn btn-primary"
            >
              Book Appointment
            </Link>

            <Link
              to="/services"
              className="btn btn-outline"
            >
              View Services
            </Link>

          </div>

        </div>

        <div className="hero__image">
          <img src={heroBarber} alt="Barber styling a client's hair" />
        </div>

      </div>
    </section>
  );
};

export default Hero;
