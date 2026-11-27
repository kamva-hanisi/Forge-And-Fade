export function catalogReadiness(services = [], barbers = []) {
  const serviceCount = services.filter((item) => item.active !== false).length;
  const barberCount = barbers.filter((item) => item.active !== false).length;
  return { serviceCount, barberCount, ready: serviceCount > 0 && barberCount > 0 };
}

const businessYear = new Intl.DateTimeFormat("en", {
  year: "numeric",
  timeZone: "Africa/Johannesburg",
});

export function businessIdentifier(name, year = businessYear.format(new Date())) {
  if (!name.trim()) return "";

  const letters = name
    .normalize("NFKD")
    .replace(/\p{Mark}/gu, "")
    .toLowerCase()
    .replace(/[^a-z]/g, "");
  const first = letters.slice(0, 2).padEnd(2, "x");
  const last = letters.slice(-2).padEnd(2, "x");
  return `${first}-${year}-${last}`;
}
