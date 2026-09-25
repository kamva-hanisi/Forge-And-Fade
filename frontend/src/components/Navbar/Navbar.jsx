import { useState } from "react";
import { Link, NavLink } from "react-router-dom";
import { Menu, X } from "lucide-react";

import logo from "../../assets/forge-and-fade-logo.png";
import "./Navbar.scss";

const Navbar = () => {
  const [open, setOpen] = useState(false);

  const closeMenu = () => {
    setOpen(false);
  };

  return (
    <header className="navbar">
      <div className="container navbar__container">

        <Link
          to="/"
          className="navbar__logo"
          onClick={closeMenu}
          aria-label="Forge & Fade home"
        >
          <img src={logo} alt="Forge & Fade" />
        </Link>

        <nav className={`navbar__nav ${open ? "open" : ""}`}>

          <NavLink to="/" onClick={closeMenu}>
            Home
          </NavLink>

          <NavLink to="/services" onClick={closeMenu}>
            Services
          </NavLink>

          <NavLink to="/about" onClick={closeMenu}>
            About
          </NavLink>

          <NavLink to="/contact" onClick={closeMenu}>
            Contact
          </NavLink>

          <Link
            to="/booking"
            className="btn btn-primary navbar__mobile-book"
            onClick={closeMenu}
          >
            Book Now
          </Link>

        </nav>

        <Link
          to="/booking"
          className="btn btn-primary navbar__book"
        >
          Book Now
        </Link>

        <button
          className="navbar__menu"
          onClick={() => setOpen(!open)}
          aria-label="Toggle menu"
        >
          {open ? <X /> : <Menu />}
        </button>

      </div>
    </header>
  );
};

export default Navbar;
