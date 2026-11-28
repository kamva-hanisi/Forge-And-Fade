import { Link } from "react-router-dom";
import { Clock3, Mail } from "lucide-react";

import "./Footer.scss";
import Brand from "../Brand/Brand";
import BusinessLink from "../BusinessLink";
import { usePublicCatalog } from "../../hooks/useShopServices";
import { shopPath } from "../../utils/shopLinks";

const Footer = () => {
  const currentYear = new Date().getFullYear();
  const catalog = usePublicCatalog();
  const shop = catalog?.shop || catalog?.requestedShop;

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

          <BusinessLink to="/">Home</BusinessLink>

          <BusinessLink to="/services">Services</BusinessLink>

          <BusinessLink to="/about">About</BusinessLink>

          <Link to={shop ? shopPath(shop) : "/booking"}>Book Appointment</Link>
          <Link to={shop ? shopPath(shop, true) : "/booking/manage"}>Manage Booking</Link>
        </div>

        {/* CONTACT */}
        <div className="footer__column">
          <h3>Contact</h3>

          <BusinessLink to="/contact" className="footer__detail">
            <Mail aria-hidden="true" />
            Contact the business
          </BusinessLink>
        </div>

        {/* HOURS */}
        <div className="footer__column">
          <h3>Opening Hours</h3>

          <BusinessLink to="/locations" className="footer__detail">
            <Clock3 aria-hidden="true" />
            Location and opening hours
          </BusinessLink>
        </div>
      </div>

      <div className="footer__bottom">
        <div className="container footer__bottom-content">
          <p>
            © {currentYear} {catalog?.profile?.name || "Business booking"}. All rights reserved.
          </p>

          <div>
            <BusinessLink to="/terms">Terms & Conditions</BusinessLink>
            <BusinessLink to="/privacy">Privacy</BusinessLink>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
