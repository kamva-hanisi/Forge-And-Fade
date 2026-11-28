import { Link } from "react-router-dom";

import barberTools from "../../assets/barber-tools.jpg";
import { shopMediaUrl } from "../../utils/shopMedia";
import { shopPath } from "../../utils/shopLinks";
import BusinessLink from "../BusinessLink";

import "./Hero.scss";

const Hero = ({ shop, name, cover, onFindTime }) => {
  return (
    <section className="hero">
      <div className="container hero__container">
        <div className="hero__content">
          <span className="section-label">{name}</span>

          <h1>
            Cuts &amp; fades.
            <br />
            <span>Beard care.</span>
          </h1>

          <p>Pick your service, book with a barber you know, or find the first available chair.</p>

          <div className="hero__buttons">
            <Link to={shopPath(shop)} className="btn btn-primary">
              Book Appointment
            </Link>

            {onFindTime ? (
              <button type="button" className="btn btn-outline" onClick={onFindTime}>
                Find a time
              </button>
            ) : (
              <BusinessLink to="/services" className="btn btn-outline">
                View Services
              </BusinessLink>
            )}
          </div>
        </div>

        <div className={`hero__image${cover ? " hero__image--uploaded" : ""}`}>
          <img src={cover ? shopMediaUrl(shop, cover) : barberTools} alt="" />
        </div>
      </div>
    </section>
  );
};

export default Hero;
