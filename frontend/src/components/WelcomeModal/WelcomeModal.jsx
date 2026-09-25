import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { X } from "lucide-react";

import "./WelcomeModal.scss";

const WelcomeModal = () => {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const alreadySeen =
      sessionStorage.getItem(
        "forgeFadeModalSeen"
      );

    if (!alreadySeen) {
      const timer = setTimeout(() => {
        setOpen(true);
      }, 1500);

      return () => clearTimeout(timer);
    }
  }, []);

  const closeModal = () => {
    setOpen(false);

    sessionStorage.setItem(
      "forgeFadeModalSeen",
      "true"
    );
  };

  if (!open) {
    return null;
  }

  return (
    <div
      className="welcome-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="welcome-title"
    >

      <div className="welcome-modal__box">

        <button
          type="button"
          className="welcome-modal__close"
          onClick={closeModal}
          aria-label="Close popup"
        >
          <X size={22} />
        </button>

        <span className="section-label">
          Forge & Fade
        </span>

        <h2 id="welcome-title">
          Online Bookings Are Open.
        </h2>

        <p>
          Reserve your chair online.
          Choose your service, barber,
          date and preferred time.
        </p>

        <Link
          to="/booking"
          className="btn btn-primary"
          onClick={closeModal}
        >
          Book Now
        </Link>

        <button
          type="button"
          className="welcome-modal__later"
          onClick={closeModal}
        >
          No Thanks
        </button>

      </div>

    </div>
  );
};

export default WelcomeModal;