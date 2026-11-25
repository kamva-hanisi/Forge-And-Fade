import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import axios from "axios";
import { Clock3, MapPin, Phone, Scissors } from "lucide-react";
import Booking from "./Booking";
import ManageBooking from "./ManageBooking";
import { directionsLink, shopPath } from "../../utils/shopLinks";
import ShopHours from "../../components/ShopHours/ShopHours";
import { shopMediaUrl } from "../../utils/shopMedia";
import "./PublicShop.scss";

function ShopDetails({ catalog }) {
  const { address, phone } = catalog.shop;

  return (
    <footer className="public-shop__footer">
      <div className="container public-shop__footer-grid">
        <div>
          <h2>{catalog.shop.name}</h2>
          {address && (
            <a href={directionsLink(address)} className="public-shop__detail">
              <MapPin size={18} aria-hidden="true" />
              {address}
            </a>
          )}
          {phone && (
            <a className="public-shop__detail" href={`tel:${phone.replace(/[^+\d]/g, "")}`}>
              <Phone size={18} aria-hidden="true" />
              {phone}
            </a>
          )}
          <div className="public-shop__policy-links">
            <Link to="/terms">Booking terms</Link>
            <Link to="/privacy">Privacy</Link>
          </div>
        </div>
        {catalog.hours && (
          <div>
            <h3 className="public-shop__detail">
              <Clock3 size={18} aria-hidden="true" /> Opening hours
            </h3>
            <ShopHours hours={catalog.hours} className="public-shop__hours" />
          </div>
        )}
      </div>
    </footer>
  );
}

function ShopPage({ slug, manage }) {
  const [catalog, setCatalog] = useState(null);
  const [error, setError] = useState("");
  const [refresh, setRefresh] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    axios
      .get(`${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/bookings/catalog`, {
        params: { shop: slug },
        signal: controller.signal,
        timeout: 20000,
      })
      .then(({ data }) => setCatalog(data))
      .catch((failure) => {
        if (!axios.isCancel(failure))
          setError(
            failure.response?.status === 404 || failure.response?.status === 422
              ? "This shop could not be found. Check the booking link with your barbershop."
              : "We could not load this shop. Check your connection and retry.",
          );
      });
    return () => controller.abort();
  }, [slug, refresh]);
  useEffect(() => {
    if (!catalog) return;
    const previousTitle = document.title;
    document.title = `${catalog.shop.name} — ${manage ? "Manage booking" : "Book appointment"}`;
    return () => {
      document.title = previousTitle;
    };
  }, [catalog, manage]);
  if (!catalog)
    return (
      <main className="public-shop__loading container">
        <h1>Shop booking</h1>
        {error ? (
          <>
            <p role="alert">{error}</p>
            <button
              className="btn btn-outline"
              onClick={() => {
                setError("");
                setRefresh((value) => value + 1);
              }}
            >
              Retry shop
            </button>
          </>
        ) : (
          <p role="status">Loading your barbershop…</p>
        )}
      </main>
    );
  const branding = catalog.branding || {};
  const style = {
    "--shop-accent": branding.accent || undefined,
    "--shop-cover": branding.cover ? `url("${shopMediaUrl(slug, branding.cover)}")` : undefined,
  };
  return (
    <div className="public-shop" style={style}>
      <header className="public-shop__top">
        <div className="public-shop__header container">
          <Link className="public-shop__brand" to={shopPath(slug)}>
            <span className={`public-shop__mark${branding.logo ? " public-shop__mark--logo" : ""}`}>
              {branding.logo ? (
                <img src={shopMediaUrl(slug, branding.logo)} alt="" />
              ) : (
                <Scissors size={20} aria-hidden="true" />
              )}
            </span>
            {catalog.shop.name}
          </Link>
          <nav className="public-shop__nav" aria-label="Shop booking navigation">
            <Link to={shopPath(slug)} aria-current={!manage ? "page" : undefined}>
              Book appointment
            </Link>
            <Link to={shopPath(slug, true)} aria-current={manage ? "page" : undefined}>
              Manage booking
            </Link>
            {!manage && catalog.barbers.some((barber) => barber.portfolio?.length) && (
              <button
                type="button"
                onClick={() => {
                  const portfolio = document.getElementById("shop-portfolio");
                  portfolio?.focus({ preventScroll: true });
                  portfolio?.scrollIntoView();
                }}
              >
                Our work
              </button>
            )}
          </nav>
        </div>
      </header>
      {manage ? (
        <ManageBooking key={slug} shop={slug} dedicated />
      ) : catalog.booking_status === "suspended" ? (
        <main className="public-shop__loading container">
          <h1>Online booking paused</h1>
          <p>
            This shop is not accepting new online bookings. Existing appointments have not been
            cancelled. Contact the shop for assistance.
          </p>
          <Link to={shopPath(slug, true)}>Manage an existing booking</Link>
        </main>
      ) : (
        <Booking key={slug} shop={slug} dedicated initialCatalog={catalog} />
      )}
      <ShopDetails catalog={catalog} />
    </div>
  );
}

export default function PublicShop({ manage = false }) {
  const { slug } = useParams();
  return <ShopPage key={`${slug}/${manage}`} slug={slug} manage={manage} />;
}
