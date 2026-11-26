import { Routes, Route, Navigate, useLocation } from "react-router-dom";

import Navbar from "./components/Navbar/Navbar";
import ScrollToTop from "./components/ScrollToTop/ScrollToTop";

import Home from "./pages/Home/Home";
import Services from "./pages/Services/Services";
import About from "./pages/About/About";
import PublicShop from "./pages/Booking/PublicShop";
import { resolveShopSlug, shopPath } from "./utils/shopLinks";
import { adminDashboard, adminLanding, rolesPath } from "./utils/adminDashboard";
import { useAdmin } from "./context/AdminContext";
import Terms from "./pages/Terms/Terms";
import Privacy from "./pages/Terms/Privacy";
import Contact from "./pages/Contact/Contact";
import Locations from "./pages/Locations/Locations";
import Footer from "./components/Footer/Footer";
import Admin from "./pages/Admin/Admin";
import AdminAccess from "./pages/Admin/AdminAccess";
import AdminLayout from "./pages/Admin/AdminLayout";
import AdminSettings from "./pages/Admin/AdminSettings";
import ShopSignup from "./pages/Admin/ShopSignup";
import AdminRecovery from "./pages/Admin/AdminRecovery";
import AdminInbox from "./pages/Admin/AdminInbox";
import AdminBooking from "./pages/Admin/AdminBooking";
import ShopManagement from "./pages/Admin/ShopManagement";
import PlatformOverview from "./pages/Admin/PlatformOverview";
import AdminRoles from "./pages/Admin/AdminRoles";

function BookingRedirect({ manage = false }) {
  const { search } = useLocation();
  const params = new URLSearchParams(search);
  const pathname = shopPath(resolveShopSlug(undefined, params.get("shop")), manage);
  params.delete("shop");
  return <Navigate to={{ pathname, search: params.toString() }} replace />;
}

function DashboardHome({ kind }) {
  const { session } = useAdmin();
  const dashboard = adminDashboard(session.user.role);
  const landing = adminLanding(session.user);
  if (landing !== dashboard.path || kind !== dashboard.kind) {
    return <Navigate to={landing} replace />;
  }

  return <AdminSettings key={dashboard.kind} dashboard={dashboard} />;
}

function Appointments({ create = false }) {
  const { session } = useAdmin();
  const permission = create ? "bookings.write" : "bookings.read";
  if (!session.user.permissions.includes(permission)) return <Navigate to={rolesPath} replace />;
  return create ? <AdminBooking /> : <Admin />;
}

function App() {
  const { pathname } = useLocation();
  const adminPage = pathname === "/admin" || pathname.startsWith("/admin/");
  const publicShopPage = pathname.startsWith("/shops/");
  const adminAccess = [
    "/admin/signin",
    "/admin/signup",
    "/admin/register-shop",
    "/admin/forgot-password",
    "/admin/reset-password",
  ].includes(pathname);
  return (
    <div className={adminAccess ? "admin-shell" : undefined}>
      <ScrollToTop />
      {!publicShopPage && (!adminPage || adminAccess) && (
        <Navbar key={pathname} admin={adminPage} />
      )}
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/services" element={<Services />} />
        <Route path="/about" element={<About />} />
        <Route path="/contact" element={<Contact />} />
        <Route path="/locations" element={<Locations />} />
        <Route path="/booking" element={<BookingRedirect />} />
        <Route path="/booking/manage" element={<BookingRedirect manage />} />
        <Route path="/shops/:slug/book" element={<PublicShop />} />
        <Route path="/shops/:slug/manage" element={<PublicShop manage />} />
        <Route
          path="/shops/*"
          element={
            <main className="public-shop__loading container">
              <h1>Booking page not found</h1>
              <p>Check the link with your barbershop.</p>
            </main>
          }
        />
        <Route path="/terms" element={<Terms />} />
        <Route path="/privacy" element={<Privacy />} />
        <Route path="/admin" element={<AdminLayout />}>
          <Route index element={<DashboardHome />} />
          <Route path="owner" element={<DashboardHome kind="owner" />} />
          <Route path="staff" element={<DashboardHome kind="staff" />} />
          <Route path="appointments" element={<Appointments />} />
          <Route path="appointments/new" element={<Appointments create />} />
          <Route path="inbox" element={<AdminInbox />} />
          <Route path="management" element={<ShopManagement />} />
          <Route path="platform" element={<PlatformOverview />} />
          <Route path="settings/profile" element={<AdminSettings key="profile" page="profile" />} />
          <Route
            path="settings/security"
            element={<AdminSettings key="security" page="security" />}
          />
          <Route path="settings/invite" element={<AdminSettings key="invite" page="invite" />} />
          <Route path="settings/roles" element={<AdminRoles />} />
          <Route path="*" element={<Navigate to="/admin" replace />} />
        </Route>
        <Route path="/admin/signin" element={<AdminAccess key="admin-signin" />} />
        <Route path="/admin/signup" element={<AdminAccess key="admin-signup" signup />} />
        <Route path="/admin/register-shop" element={<ShopSignup />} />
        <Route path="/admin/forgot-password" element={<AdminRecovery key="forgot" />} />
        <Route path="/admin/reset-password" element={<AdminRecovery key="reset" reset />} />
        <Route path="/signin" element={<Navigate to="/admin/signin" replace />} />
        <Route path="/signup" element={<Navigate to="/admin/signup" replace />} />
        <Route path="/account" element={<Navigate to="/admin/signin" replace />} />
      </Routes>
      {!adminPage && !publicShopPage && <Footer />}
    </div>
  );
}

export default App;
