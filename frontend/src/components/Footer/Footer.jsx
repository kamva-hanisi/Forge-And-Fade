import { Link } from "react-router-dom";
import { Clock3, Mail } from "lucide-react";

import "./Footer.scss";
import Brand from "../Brand/Brand";

const Footer = () => {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="footer">
      <div className="container footer__grid">
        {/* BRAND */}
        <div className="footer__brand">
          <Brand dark />

          <p>
            Professional cuts, clean fades and beard grooming in a comfortable modern barbershop.
          </p>
        </div>

        {/* NAVIGATION */}
        <div className="footer__column">
          <h3>Quick Links</h3>

          <Link to="/">Home</Link>

          <Link to="/services">Services</Link>

          <Link to="/about">About</Link>

          <Link to="/booking">Book Appointment</Link>
          <Link to="/booking/manage">Manage Booking</Link>
        </div>

        {/* CONTACT */}
        <div className="footer__column">
          <h3>Contact</h3>

          <Link to="/contact" className="footer__detail">
            <Mail aria-hidden="true" />
            Contact the shop
          </Link>
        </div>

        {/* HOURS */}
        <div className="footer__column">
          <h3>Opening Hours</h3>

          <Link to="/locations" className="footer__detail">
            <Clock3 aria-hidden="true" />
            Locations and opening hours
          </Link>
        </div>
      </div>

      <div className="footer__bottom">
        <div className="container footer__bottom-content">
          <p>© {currentYear} Forge & Fade. All rights reserved.</p>

          <div>
            <Link to="/terms">Terms & Conditions</Link>
            <Link to="/privacy">Privacy</Link>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
