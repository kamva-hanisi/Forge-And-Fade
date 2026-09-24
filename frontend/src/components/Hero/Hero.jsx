import { Link } from "react-router-dom";
import {
  ArrowRight,
  CalendarDays,
  Clock3,
  MapPin,
} from "lucide-react";

import "./Hero.scss";

const Hero = () => {
  return (
    <section className="hero">

      <div className="hero__background"></div>

      <div className="container hero__container">

        {/* LEFT */}
        <div className="hero__content">

          <div className="hero__eyebrow">
            <span></span>
            Premium Men's Grooming
          </div>

          <h1 className="hero__title">
            Modern Cuts.
            <br />

            <span>
              Timeless Craft.
            </span>
          </h1>

          <p className="hero__description">
            Precision haircuts, clean fades and traditional grooming
            crafted with detail, character and confidence.
          </p>

          <div className="hero__actions">

            <Link
              to="/booking"
              className="btn btn-primary hero__primary"
            >
              Book Your Chair

              <ArrowRight size={17} />
            </Link>

            <Link
              to="/services"
              className="btn btn-outline"
            >
              Explore Services
            </Link>

          </div>

          {/* Info */}
          <div className="hero__info">

            <div className="hero__info-item">
              <Clock3 size={20} />

              <div>
                <span>Opening Hours</span>
                <strong>Mon – Sat · 08:00 – 18:00</strong>
              </div>
            </div>

            <div className="hero__info-item">
              <MapPin size={20} />

              <div>
                <span>Visit The Shop</span>
                <strong>Cape Town, South Africa</strong>
              </div>
            </div>

          </div>

        </div>

        {/* RIGHT */}
        <div className="hero__visual">

          <div className="hero__image-frame">

            <div className="hero__image-placeholder">

              <div className="hero__monogram">
                <span>F</span>

                <div className="hero__monogram-line"></div>

                <span>F</span>
              </div>

            </div>

            {/* Floating booking card */}
            <div className="hero__booking-card">

              <div className="hero__booking-icon">
                <CalendarDays size={20} />
              </div>

              <div>
                <span>Appointments Available</span>
                <strong>Reserve Your Chair</strong>
              </div>

            </div>

          </div>

          <div className="hero__outline-text">
            EST. 2026
          </div>

        </div>

      </div>

      {/* Bottom Decoration */}
      <div className="hero__scroll">
        <span>SCROLL TO DISCOVER</span>

        <div className="hero__scroll-line"></div>
      </div>

    </section>
  );
};

export default Hero;