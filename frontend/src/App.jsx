import { Routes, Route, Link, Navigate, useLocation } from "react-router-dom";

import Navbar from "./components/Navbar/Navbar";
import ScrollToTop from "./components/ScrollToTop/ScrollToTop";

import Home from "./pages/Home/Home";
import Services from "./pages/Services/Services";
import About from "./pages/About/About";
import Booking from "./pages/Booking/Booking";
import Terms from "./pages/Terms/Terms";
import Contact from "./pages/Contact/Contact";
import Footer from "./components/Footer/Footer";
import Admin from "./pages/Admin/Admin";
import AdminAccess from "./pages/Admin/AdminAccess";

function App() {
  const { pathname } = useLocation();
  const adminPage = pathname === "/admin" || pathname.startsWith("/admin/");
  return (
    <div className={adminPage ? "admin-shell" : undefined}>
      <ScrollToTop />
      {adminPage ? <header className="admin-brand"><Link to="/">Forge & Fade</Link><Link to="/admin">Administration</Link></header> : <Navbar />}
      <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/services" element={<Services />} />
      <Route path="/about" element={<About />} />
      <Route path="/contact" element={<Contact />} />
      <Route path="/booking" element={<Booking />} />
      <Route path="/terms" element={<Terms />} />
      <Route path="/admin" element={<Admin />} />
      <Route path="/admin/signin" element={<AdminAccess key="admin-signin" />} />
      <Route path="/admin/signup" element={<AdminAccess key="admin-signup" signup />} />
      <Route path="/signin" element={<Navigate to="/admin/signin" replace />} />
      <Route path="/signup" element={<Navigate to="/admin/signup" replace />} />
      <Route path="/account" element={<Navigate to="/admin/signin" replace />} />
      
    </Routes>
      {!adminPage && <Footer />}
    </div>
  );
}

export default App;
