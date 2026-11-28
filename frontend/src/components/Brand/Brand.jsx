import logo from "../../assets/forge-and-fade-logo.png";
import { shopMediaUrl } from "../../utils/shopMedia";
import BusinessLink from "../BusinessLink";
import "./Brand.scss";

export default function Brand({ onClick, dark = false, business, logoFile }) {
  const name = business?.name || "Business booking";
  const image =
    business && logoFile
      ? shopMediaUrl(business.slug, logoFile)
      : business?.slug === "forge-and-fade"
        ? logo
        : null;
  return (
    <BusinessLink
      to="/"
      className={`brand${dark ? " brand--dark" : ""}`}
      onClick={onClick}
      aria-label={`${name} home`}
    >
      {image ? (
        <img
          className={business && logoFile ? "brand__image--uploaded" : undefined}
          src={image}
          alt={name}
          width="200"
          height="72"
        />
      ) : (
        <span>{name}</span>
      )}
    </BusinessLink>
  );
}
