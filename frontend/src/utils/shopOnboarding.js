export function catalogReadiness(services = [], barbers = []) {
  const serviceCount = services.filter((item) => item.active !== false).length;
  const barberCount = barbers.filter((item) => item.active !== false).length;
  return { serviceCount, barberCount, ready: serviceCount > 0 && barberCount > 0 };
}
