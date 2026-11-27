import { Link } from "react-router-dom";
import logo from "../../assets/forge-and-fade-logo.png";
import { shopMediaUrl } from "../../utils/shopMedia";
import "./Brand.scss";

export default function Brand({ onClick, dark = false, business, logoFile }) {
  const name = business?.name || "Forge & Fade";
  const image =
    business && logoFile
      ? shopMediaUrl(business.slug, logoFile)
      : !business || business.slug === "forge-and-fade"
        ? logo
        : null;
  return (
    <Link
      to="/"
      className={`brand${dark ? " brand--dark" : ""}`}
      onClick={onClick}
      aria-label={`${name} home`}
    >
      {image ? <img src={image} alt={name} width="200" height="72" /> : <span>{name}</span>}
    </Link>
  );
}
