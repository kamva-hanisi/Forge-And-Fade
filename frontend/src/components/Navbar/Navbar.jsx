import { useEffect, useState } from "react";
import { NavLink } from "react-router-dom";
import { Menu, X } from "lucide-react";
import Brand from "../Brand/Brand";
import BusinessLink from "../BusinessLink";
import { useAdmin } from "../../context/AdminContext";
import { usePublicCatalog } from "../../hooks/useShopServices";
import "./Navbar.scss";

export default function Navbar({ admin = false }) {
  const [open, setOpen] = useState(false);
  const { session } = useAdmin();
  const catalog = usePublicCatalog();
  const closeMenu = () => setOpen(false);
  useEffect(() => {
    if (!open) return;
    const closeOnEscape = (event) => {
      if (event.key === "Escape") setOpen(false);
    };
    window.addEventListener("keydown", closeOnEscape);
    return () => window.removeEventListener("keydown", closeOnEscape);
  }, [open]);
  const action = admin ? { to: "/", label: "View website" } : { to: "/booking", label: "Book now" };

  return (
    <header className="navbar">
      <div className="container navbar__container">
        <Brand onClick={closeMenu} business={catalog?.profile} logoFile={catalog?.branding?.logo} />
        <nav
          id="site-navigation"
          className={`navbar__nav${open ? " open" : ""}`}
          aria-label={admin ? "Admin navigation" : "Main navigation"}
        >
          {admin ? (
            <>
              <NavLink to="/admin" end onClick={closeMenu}>
                Dashboard
              </NavLink>
              {!session && (
                <>
                  <NavLink to="/admin/signin" onClick={closeMenu}>
                    Admin sign in
                  </NavLink>
                  <NavLink to="/admin/signup" onClick={closeMenu}>
                    Staff sign up
                  </NavLink>
                </>
              )}
            </>
          ) : (
            <>
              <BusinessLink as={NavLink} to="/" end onClick={closeMenu}>
                Home
              </BusinessLink>
              <BusinessLink as={NavLink} to="/services" onClick={closeMenu}>
                Services
              </BusinessLink>
              <BusinessLink as={NavLink} to="/about" onClick={closeMenu}>
                About
              </BusinessLink>
              <BusinessLink as={NavLink} to="/locations" onClick={closeMenu}>
                Location
              </BusinessLink>
              <BusinessLink as={NavLink} to="/contact" onClick={closeMenu}>
                Contact
              </BusinessLink>
            </>
          )}
          <BusinessLink
            to={action.to}
            className="btn btn-primary navbar__mobile-book"
            onClick={closeMenu}
          >
            {action.label}
          </BusinessLink>
        </nav>
        <BusinessLink to={action.to} className="btn btn-primary navbar__book" onClick={closeMenu}>
          {action.label}
        </BusinessLink>
        <button
          type="button"
          className="icon-button navbar__menu"
          onClick={() => setOpen(!open)}
          aria-label={open ? "Close menu" : "Open menu"}
          aria-controls="site-navigation"
          aria-expanded={open}
        >
          {open ? <X size={22} aria-hidden="true" /> : <Menu size={22} aria-hidden="true" />}
        </button>
      </div>
    </header>
  );
}
