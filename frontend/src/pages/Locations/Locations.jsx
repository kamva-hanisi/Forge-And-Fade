import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import axios from "axios";
import { ArrowUpRight, Clock3, MapPin, Phone } from "lucide-react";
import barberTools from "../../assets/barber-tools.jpg";
import ShopHours from "../../components/ShopHours/ShopHours";
import { shopDirections, shopPath, shopStreetAddress } from "../../utils/shopLinks";
import { shopMediaUrl } from "../../utils/shopMedia";
import "./Locations.scss";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
const closureDate = new Intl.DateTimeFormat("en-ZA", {
  day: "numeric",
  month: "short",
  year: "numeric",
  timeZone: "UTC",
});

function LocationCard({ shop }) {
  const directions = shopDirections(shop);
  const titleId = `location-${shop.slug}`;

  return (
    <article className="location-card" aria-labelledby={titleId}>
      <div className="location-card__info">
        {shop.cover && (
          <img
            className="location-card__cover"
            src={shopMediaUrl(shop.slug, shop.cover)}
            alt=""
            loading="lazy"
          />
        )}
        <h2 id={titleId}>{shop.name}</h2>
        <div className="location-card__address">
          <MapPin size={21} aria-hidden="true" />
          <div>
            {shop.address && <p>{shop.address}</p>}
            {!shopStreetAddress(shop) && (
              <p className="location-card__muted">Street address coming soon.</p>
            )}
          </div>
        </div>
        {shop.phone && (
          <a className="location-card__phone" href={`tel:${shop.phone.replace(/[^+\d]/g, "")}`}>
            <Phone size={18} aria-hidden="true" />
            {shop.phone}
          </a>
        )}
        {(directions || shop.can_book) && (
          <div className="location-card__actions">
            {directions && (
              <a href={directions} className="btn btn-primary">
                Get directions <ArrowUpRight size={16} aria-hidden="true" />
              </a>
            )}
            {shop.can_book && (
              <Link
                to={shopPath(shop.slug)}
                className={`btn ${directions ? "btn-outline" : "btn-primary"}`}
              >
                Book appointment
              </Link>
            )}
          </div>
        )}
      </div>
      <div className="location-card__schedule">
        <h3>
          <Clock3 size={20} aria-hidden="true" /> Opening hours
        </h3>
        <ShopHours hours={shop.hours} className="location-card__hours" />
        <p className="location-card__muted">Local time · {shop.timezone.replaceAll("_", " ")}</p>
        {shop.upcoming_closures.length > 0 && (
          <p className="location-card__closure">
            Closed on{" "}
            {shop.upcoming_closures
              .map((date) => closureDate.format(new Date(`${date}T00:00:00Z`)))
              .join(", ")}
            .
          </p>
        )}
      </div>
    </article>
  );
}

export default function Locations() {
  const [result, setResult] = useState(null);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    axios
      .get(`${apiUrl}/locations`, { signal: controller.signal, timeout: 20000 })
      .then(({ data }) => setResult(data.locations))
      .catch(() => {
        if (!controller.signal.aborted) setResult(false);
      });
    return () => controller.abort();
  }, [attempt]);

  return (
    <main className="locations-page">
      <header className="locations-page__hero">
        <div className="container locations-page__hero-inner">
          <div>
            <h1>Business locations</h1>
            <p>Find a business, check opening hours, and plan your visit.</p>
          </div>
          <img src={barberTools} alt="" />
        </div>
      </header>
      <div className="container locations-page__list">
        {result === null && <p role="status">Loading business locations…</p>}
        {result === false && (
          <div className="locations-page__state">
            <p role="alert">Business locations are temporarily unavailable.</p>
            <button
              type="button"
              className="btn btn-outline"
              onClick={() => {
                setResult(null);
                setAttempt((value) => value + 1);
              }}
            >
              Try again
            </button>
          </div>
        )}
        {result?.length === 0 && <p>There are no active business locations to display.</p>}
        {Array.isArray(result) &&
          result.map((shop) => <LocationCard key={shop.slug} shop={shop} />)}
      </div>
    </main>
  );
}
