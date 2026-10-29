import { Link } from "react-router-dom";
import logo from "../../assets/forge-and-fade-logo.png";
import "./Brand.scss";

export default function Brand({ onClick, dark = false }) {
  return <Link to="/" className={`brand${dark ? " brand--dark" : ""}`} onClick={onClick} aria-label="Forge & Fade home"><img src={logo} alt="Forge & Fade" width="200" height="72" /></Link>;
}
