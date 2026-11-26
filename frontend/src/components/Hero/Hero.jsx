import { Link } from "react-router-dom";

import barberTools from "../../assets/barber-tools.jpg";
import { shopMediaUrl } from "../../utils/shopMedia";

import "./Hero.scss";

const Hero = ({ shop, cover, onFindTime }) => {
  return (
    <section className="hero">
      <div className="container hero__container">
        <div className="hero__content">
          <span className="section-label">Forge &amp; Fade</span>

          <h1>
            Cuts &amp; fades.
            <br />
            <span>Beard care.</span>
          </h1>

          <p>Pick your service, book with a barber you know, or find the first available chair.</p>

          <div className="hero__buttons">
            <Link to="/booking" className="btn btn-primary">
              Book Appointment
            </Link>

            {onFindTime ? (
              <button type="button" className="btn btn-outline" onClick={onFindTime}>
                Find a time
              </button>
            ) : (
              <Link to="/services" className="btn btn-outline">
                View Services
              </Link>
            )}
          </div>
        </div>

        <div className="hero__image">
          <img src={cover ? shopMediaUrl(shop, cover) : barberTools} alt="" />
        </div>
      </div>
    </section>
  );
};

export default Hero;
