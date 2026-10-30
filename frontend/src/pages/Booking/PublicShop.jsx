import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import axios from "axios";
import Booking from "./Booking";
import ManageBooking from "./ManageBooking";
import { shopPath } from "../../utils/shopLinks";
import "./PublicShop.scss";

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
  return (
    <div className="public-shop">
      <header className="public-shop__header container">
        <Link className="public-shop__brand" to={shopPath(slug)}>
          {catalog.shop.name}
        </Link>
        <nav aria-label="Shop booking navigation">
          <Link to={shopPath(slug)} aria-current={!manage ? "page" : undefined}>
            Book appointment
          </Link>
          <Link to={shopPath(slug, true)} aria-current={manage ? "page" : undefined}>
            Manage booking
          </Link>
        </nav>
      </header>
      {manage ? (
        <ManageBooking key={slug} shop={slug} dedicated />
      ) : (
        <Booking key={slug} shop={slug} dedicated initialCatalog={catalog} />
      )}
      <footer className="public-shop__footer container">
        <h2>{catalog.shop.name}</h2>
        {catalog.shop.address && <p>{catalog.shop.address}</p>}
        {catalog.shop.phone && (
          <a href={`tel:${catalog.shop.phone.replace(/[^+\d]/g, "")}`}>{catalog.shop.phone}</a>
        )}
        <p>Appointment times: {catalog.timezone}. Contact the shop for changes or assistance.</p>
      </footer>
    </div>
  );
}

export default function PublicShop({ manage = false }) {
  const { slug } = useParams();
  return <ShopPage key={`${slug}/${manage}`} slug={slug} manage={manage} />;
}
