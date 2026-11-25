export function shopPath(slug, manage = false) {
  return `/shops/${encodeURIComponent(slug)}/${manage ? "manage" : "book"}`;
}

export function resolveShopSlug(routeSlug, querySlug) {
  return routeSlug || querySlug || "forge-and-fade";
}

export function bookingLink(slug, selection = {}) {
  const search = new URLSearchParams(selection).toString();
  return search ? `${shopPath(slug)}?${search}` : shopPath(slug);
}

export function directionsLink(address) {
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
}

export function initialBookingSelection(params, catalog) {
  const service = params.get("service") || "";
  const barber = params.get("barber") || "";
  const date = params.get("date") || "";
  const time = params.get("time") || "";
  const day = /^\d{4}-\d{2}-\d{2}$/.test(date) ? new Date(`${date}T12:00:00Z`) : null;
  const validDate = day && !Number.isNaN(day.valueOf()) && day.toISOString().slice(0, 10) === date;
  const serviceExists = catalog?.services.some((item) => item.id === Number(service));
  const barberExists =
    barber === "any"
      ? catalog?.barbers.length > 0
      : catalog?.barbers.some((item) => item.id === Number(barber));
  const hasSuggestion =
    serviceExists && barberExists && validDate && /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(time);

  return {
    service_id: service,
    barber_id: barber,
    booking_date: hasSuggestion ? date : "",
    booking_time: hasSuggestion ? time : "",
  };
}
