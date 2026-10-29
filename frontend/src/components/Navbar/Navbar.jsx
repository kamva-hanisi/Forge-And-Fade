import { useEffect, useState } from "react";
import { Link, NavLink } from "react-router-dom";
import { Menu, X } from "lucide-react";
import Brand from "../Brand/Brand";
import { useAdmin } from "../../context/AdminContext";
import "./Navbar.scss";

export default function Navbar({ admin = false }) {
  const [open, setOpen] = useState(false);
  const { session } = useAdmin();
  const closeMenu = () => setOpen(false);
  useEffect(() => {
    if (!open) return;
    const closeOnEscape = (event) => { if (event.key === "Escape") setOpen(false); };
    window.addEventListener("keydown", closeOnEscape);
    return () => window.removeEventListener("keydown", closeOnEscape);
  }, [open]);
  const action = admin ? { to: "/", label: "View website" } : { to: "/booking", label: "Book now" };

  return <header className="navbar"><div className="container navbar__container">
    <Brand onClick={closeMenu} />
    <nav id="site-navigation" className={`navbar__nav${open ? " open" : ""}`} aria-label={admin ? "Admin navigation" : "Main navigation"}>
      {admin ? <>
        <NavLink to="/admin" end onClick={closeMenu}>Dashboard</NavLink>
        {!session && <><NavLink to="/admin/signin" onClick={closeMenu}>Admin sign in</NavLink><NavLink to="/admin/signup" onClick={closeMenu}>Admin sign up</NavLink></>}
      </> : <>
        <NavLink to="/" end onClick={closeMenu}>Home</NavLink>
        <NavLink to="/services" onClick={closeMenu}>Services</NavLink>
        <NavLink to="/about" onClick={closeMenu}>About</NavLink>
        <NavLink to="/contact" onClick={closeMenu}>Contact</NavLink>
        <NavLink to={session ? "/admin" : "/admin/signin"} onClick={closeMenu}>Admin</NavLink>
      </>}
      <Link to={action.to} className="btn btn-primary navbar__mobile-book" onClick={closeMenu}>{action.label}</Link>
    </nav>
    <Link to={action.to} className="btn btn-primary navbar__book" onClick={closeMenu}>{action.label}</Link>
    <button type="button" className="icon-button navbar__menu" onClick={() => setOpen(!open)} aria-label={open ? "Close menu" : "Open menu"} aria-controls="site-navigation" aria-expanded={open}>{open ? <X size={22} aria-hidden="true" /> : <Menu size={22} aria-hidden="true" />}</button>
  </div></header>;
}
