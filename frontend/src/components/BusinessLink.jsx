import { Link } from "react-router-dom";
import { usePublicCatalog } from "../hooks/useShopServices";
import { publicBusinessPath } from "../utils/shopLinks";

export default function BusinessLink({ as: LinkComponent = Link, to, shop, ...props }) {
  const catalog = usePublicCatalog();
  return <LinkComponent to={publicBusinessPath(to, shop ?? catalog?.requestedShop)} {...props} />;
}
