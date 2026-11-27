import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { MapPin } from "lucide-react";
import Hero from "../../components/Hero/Hero";
import ServicesSection from "../../components/ServicesSection/ServicesSection";
import AboutSection from "../../components/AboutSection/AboutSection";
import { usePublicCatalog } from "../../hooks/useShopServices";
import NextAvailable from "../Booking/NextAvailable";
import { bookingLink, shopPath } from "../../utils/shopLinks";
import "./Home.scss";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

function QuickBooking({ catalog }) {
  const [service, setService] = useState("");
  const navigate = useNavigate();
  if (!catalog.services.length || !catalog.barbers.length) return null;

  return (
    <section className="home-availability">
      <div className="container home-availability__grid">
        <div>
          <h2 className="section-title">Find a time for your next cut</h2>
          <p>See the earliest appointments for your service over the next seven days.</p>
        </div>
        <div>
          <label>
            Choose a service
            <select
              id="home-booking-service"
              value={service}
              onChange={(event) => setService(event.target.value)}
            >
              <option value="">Select a service</option>
              {catalog.services.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>
          </label>
          {service && catalog.services.some((item) => item.id === Number(service)) && (
            <NextAvailable
              key={service}
              apiUrl={apiUrl}
              shop={catalog.shop}
              service={service}
              barber="any"
              onSelect={(slot, barber) =>
                navigate(
                  bookingLink(catalog.shop, {
                    service,
                    barber,
                    date: slot.booking_date,
                    time: slot.booking_time,
                  }),
                )
              }
            />
          )}
        </div>
      </div>
    </section>
  );
}

const Home = () => {
  const catalog = usePublicCatalog();
  const canFindTime = catalog.services.length > 0 && catalog.barbers.length > 0;
  const findTime = () => {
    const servicePicker = document.getElementById("home-booking-service");
    servicePicker?.focus({ preventScroll: true });
    servicePicker?.scrollIntoView({ block: "center" });
  };
  return (
    <main>
      <Hero
        shop={catalog.shop}
        name={catalog.profile.name}
        cover={catalog.branding?.cover}
        onFindTime={canFindTime ? findTime : null}
      />
      <QuickBooking catalog={catalog} />
      <ServicesSection catalog={catalog} />
      <AboutSection name={catalog.profile.name} />
      <section className="home-visit">
        <div className="container home-visit__inner">
          <div>
            <h2 className="section-title">Plan your visit</h2>
            {catalog.profile?.address && (
              <p>
                <MapPin size={18} aria-hidden="true" /> {catalog.profile.address}
              </p>
            )}
          </div>
          <div className="home-visit__actions">
            <Link
              to={`/contact?shop=${encodeURIComponent(catalog.shop)}`}
              className="btn btn-primary"
            >
              Hours and directions
            </Link>
            <Link to={shopPath(catalog.shop, true)} className="btn btn-outline">
              Manage a booking
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
};

export default Home;
