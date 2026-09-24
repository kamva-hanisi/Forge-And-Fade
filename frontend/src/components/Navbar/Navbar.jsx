import { useState } from "react";
import { NavLink, Link } from "react-router-dom";
import { Menu, X, Scissors } from "lucide-react";

import "./Navbar.scss";

const Navbar = () => {
  const [menuOpen, setMenuOpen] = useState(false);

  const closeMenu = () => {
    setMenuOpen(false);
  };

  return (
    <header className="navbar">
      <div className="container navbar__container">

        {/* Logo */}
        <Link to="/" className="navbar__logo" onClick={closeMenu}>
          <div className="navbar__logo-icon">
            <Scissors size={22} strokeWidth={1.7} />
          </div>

          <div className="navbar__logo-text">
            <span className="navbar__brand">FORGE & FADE</span>
            <span className="navbar__tagline">BARBERSHOP</span>
          </div>
        </Link>

        {/* Desktop / Mobile Navigation */}
        <nav
          className={`navbar__nav ${
            menuOpen ? "navbar__nav--open" : ""
          }`}
        >
          <NavLink
            to="/"
            onClick={closeMenu}
            className={({ isActive }) =>
              isActive ? "navbar__link active" : "navbar__link"
            }
          >
            Home
          </NavLink>

          <NavLink
            to="/services"
            onClick={closeMenu}
            className={({ isActive }) =>
              isActive ? "navbar__link active" : "navbar__link"
            }
          >
            Services
          </NavLink>

          <NavLink
            to="/about"
            onClick={closeMenu}
            className={({ isActive }) =>
              isActive ? "navbar__link active" : "navbar__link"
            }
          >
            About
          </NavLink>

          <NavLink
            to="/booking"
            onClick={closeMenu}
            className={({ isActive }) =>
              isActive ? "navbar__link active" : "navbar__link"
            }
          >
            Booking
          </NavLink>

          {/* Mobile Book Button */}
          <Link
            to="/booking"
            onClick={closeMenu}
            className="btn btn-primary navbar__mobile-book"
          >
            Book Now
          </Link>
        </nav>

        {/* Desktop Book Now */}
        <Link to="/booking" className="btn btn-primary navbar__book">
          Book Now
        </Link>

        {/* Mobile Menu Button */}
        <button
          className="navbar__toggle"
          onClick={() => setMenuOpen(!menuOpen)}
          aria-label="Toggle navigation"
          aria-expanded={menuOpen}
        >
          {menuOpen ? <X size={26} /> : <Menu size={26} />}
        </button>

      </div>
    </header>
  );
};

export default Navbar;