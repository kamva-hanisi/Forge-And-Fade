export function shopPath(slug, manage = false) {
  return `/shops/${encodeURIComponent(slug)}/${manage ? "manage" : "book"}`;
}

export function resolveShopSlug(routeSlug, querySlug) {
  return routeSlug || querySlug || "forge-and-fade";
}
