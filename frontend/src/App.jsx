import { Routes, Route, Navigate, useLocation } from "react-router-dom";

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
import AdminLayout from "./pages/Admin/AdminLayout";
import AdminSettings from "./pages/Admin/AdminSettings";
import ShopSignup from "./pages/Admin/ShopSignup";

function App() {
  const { pathname } = useLocation();
  const adminPage = pathname === "/admin" || pathname.startsWith("/admin/");
  const adminAccess = ["/admin/signin", "/admin/signup", "/admin/register-shop"].includes(pathname);
  return (
    <div className={adminAccess ? "admin-shell" : undefined}>
      <ScrollToTop />
      {(!adminPage || adminAccess) && <Navbar key={pathname} admin={adminPage} />}
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/services" element={<Services />} />
        <Route path="/about" element={<About />} />
        <Route path="/contact" element={<Contact />} />
        <Route path="/booking" element={<Booking />} />
        <Route path="/terms" element={<Terms />} />
        <Route path="/admin" element={<AdminLayout />}>
          <Route index element={<AdminSettings key="dashboard" />} />
          <Route path="appointments" element={<Admin />} />
          <Route path="settings/profile" element={<AdminSettings key="profile" page="profile" />} />
          <Route
            path="settings/security"
            element={<AdminSettings key="security" page="security" />}
          />
          <Route path="settings/invite" element={<AdminSettings key="invite" page="invite" />} />
          <Route path="*" element={<Navigate to="/admin" replace />} />
        </Route>
        <Route path="/admin/signin" element={<AdminAccess key="admin-signin" />} />
        <Route path="/admin/signup" element={<AdminAccess key="admin-signup" signup />} />
        <Route path="/admin/register-shop" element={<ShopSignup />} />
        <Route path="/signin" element={<Navigate to="/admin/signin" replace />} />
        <Route path="/signup" element={<Navigate to="/admin/signup" replace />} />
        <Route path="/account" element={<Navigate to="/admin/signin" replace />} />
      </Routes>
      {!adminPage && <Footer />}
    </div>
  );
}

export default App;
