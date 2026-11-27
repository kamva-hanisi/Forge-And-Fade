import { Link } from "react-router-dom";
import { Clock3, Mail } from "lucide-react";

import "./Footer.scss";
import Brand from "../Brand/Brand";
import { usePublicCatalog } from "../../hooks/useShopServices";
import { shopPath } from "../../utils/shopLinks";

const Footer = () => {
  const currentYear = new Date().getFullYear();
  const catalog = usePublicCatalog();
  const shop = catalog?.shop;

  return (
    <footer className="footer">
      <div className="container footer__grid">
        {/* BRAND */}
        <div className="footer__brand">
          <Brand dark business={catalog?.profile} logoFile={catalog?.branding?.logo} />

          <p>Professional cuts, clean fades and beard grooming in a comfortable, modern setting.</p>
        </div>

        {/* NAVIGATION */}
        <div className="footer__column">
          <h3>Quick Links</h3>

          <Link to="/">Home</Link>

          <Link to="/services">Services</Link>

          <Link to="/about">About</Link>

          <Link to={shop ? shopPath(shop) : "/booking"}>Book Appointment</Link>
          <Link to={shop ? shopPath(shop, true) : "/booking/manage"}>Manage Booking</Link>
        </div>

        {/* CONTACT */}
        <div className="footer__column">
          <h3>Contact</h3>

          <Link to="/contact" className="footer__detail">
            <Mail aria-hidden="true" />
            Contact the business
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
          <p>
            © {currentYear} {catalog?.profile?.name || "Forge & Fade"}. All rights reserved.
          </p>

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
