import { Link } from "react-router-dom";
import {
  Camera,
  Clock3,
  Mail,
  MapPin,
  Phone,
  UsersRound,
} from "lucide-react";

import "./Footer.scss";

const Footer = () => {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="footer">

      <div className="container footer__grid">

        {/* BRAND */}
        <div className="footer__brand">

          <Link
            to="/"
            className="footer__logo"
          >
            FORGE <span>&</span> FADE
          </Link>

          <p>
            Professional cuts, clean fades and beard
            grooming in a comfortable modern barbershop.
          </p>

          <div className="footer__socials">
            <a
              href="https://www.instagram.com/"
              target="_blank"
              rel="noreferrer"
              aria-label="Forge & Fade on Instagram"
              title="Instagram"
            >
              <Camera aria-hidden="true" />
            </a>

            <a
              href="https://www.facebook.com/"
              target="_blank"
              rel="noreferrer"
              aria-label="Forge & Fade on Facebook"
              title="Facebook"
            >
              <UsersRound aria-hidden="true" />
            </a>
          </div>

        </div>

        {/* NAVIGATION */}
        <div className="footer__column">

          <h3>Quick Links</h3>

          <Link to="/">
            Home
          </Link>

          <Link to="/services">
            Services
          </Link>

          <Link to="/about">
            About
          </Link>

          <Link to="/contact">
            Contact
          </Link>

          <Link to="/booking">
            Book Appointment
          </Link>

        </div>

        {/* CONTACT */}
        <div className="footer__column">

          <h3>Contact</h3>

          <a
            href="tel:+27651360694"
            className="footer__detail"
          >
            <Phone aria-hidden="true" />
            +27 65 136 0694
          </a>

          <a
            href="mailto:kamva@forgeandfade.co.za"
            className="footer__detail"
          >
            <Mail aria-hidden="true" />
            kamva@forgeandfade.co.za
          </a>

          <div className="footer__detail">
            <MapPin aria-hidden="true" />
            <p>
              Cape Town,
              <br />
              South Africa
            </p>
          </div>

        </div>

        {/* HOURS */}
        <div className="footer__column">

          <h3>Opening Hours</h3>

          <div className="footer__detail footer__detail--hours">
            <Clock3 aria-hidden="true" />
            <div>
              <p>Monday – Saturday</p>
              <p>08:00 – 18:00</p>
              <p>Sunday: Closed</p>
            </div>
          </div>

          {/* <Link
            to="/booking"
            className="footer__book"
          >
            <CalendarDays aria-hidden="true" />
            Book Now
          </Link> */}

        </div>

      </div>

      <div className="footer__bottom">

        <div className="container footer__bottom-content">

          <p>
            © {currentYear} Forge & Fade.
            All rights reserved.
          </p>

          <div>
            <Link to="/terms">
              Terms & Conditions
            </Link>

            <Link to="/contact">
              Contact
            </Link>
          </div>

        </div>

      </div>

    </footer>
  );
};

export default Footer;
