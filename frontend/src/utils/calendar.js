const SHOP_NAME = "Forge & Fade";
const SHOP_LOCATION = "Cape Town, South Africa";
const TIME_ZONE = "Africa/Johannesburg";

const cleanDate = (date) => {
  return String(date).split("T")[0];
};

const cleanTime = (time) => {
  return String(time).slice(0, 5);
};

const createAppointmentDates = (booking) => {
  const date = cleanDate(booking.booking_date);
  const time = cleanTime(booking.booking_time);

  const start = new Date(`${date}T${time}:00`);

  const end = new Date(start.getTime() + Number(booking.duration) * 60 * 1000);

  return {
    start,
    end,
  };
};

const formatCalendarDate = (date) => {
  const pad = (number) => String(number).padStart(2, "0");

  return (
    date.getFullYear() +
    pad(date.getMonth() + 1) +
    pad(date.getDate()) +
    "T" +
    pad(date.getHours()) +
    pad(date.getMinutes()) +
    pad(date.getSeconds())
  );
};

const formatUtcDate = (date) => {
  return date
    .toISOString()
    .replace(/[-:]/g, "")
    .replace(/\.\d{3}/, "");
};

const escapeIcsText = (text = "") => {
  return String(text)
    .replace(/\\/g, "\\\\")
    .replace(/,/g, "\\,")
    .replace(/;/g, "\\;")
    .replace(/\n/g, "\\n");
};

export const openGoogleCalendar = (booking) => {
  const { start, end } = createAppointmentDates(booking);

  const params = new URLSearchParams({
    action: "TEMPLATE",

    text: `${SHOP_NAME} - ${booking.service_name}`,

    dates: `${formatCalendarDate(start)}/${formatCalendarDate(end)}`,

    details:
      `Appointment with ${booking.barber_name}. ` +
      `Service: ${booking.service_name}. ` +
      `Customer: ${booking.customer_name}.`,

    location: SHOP_LOCATION,

    ctz: TIME_ZONE,
  });

  window.open(
    `https://calendar.google.com/calendar/render?${params.toString()}`,
    "_blank",
    "noopener,noreferrer",
  );
};

export const downloadAppleCalendar = (booking) => {
  const { start, end } = createAppointmentDates(booking);

  const description =
    `Appointment with ${booking.barber_name}. ` +
    `Service: ${booking.service_name}.`;

  const icsContent = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "CALSCALE:GREGORIAN",
    "METHOD:PUBLISH",
    "PRODID:-//Forge & Fade//Booking Calendar//EN",

    "BEGIN:VEVENT",

    `UID:${booking.id}-${Date.now()}@forgeandfade.co.za`,

    `DTSTAMP:${formatUtcDate(new Date())}`,

    `DTSTART;TZID=${TIME_ZONE}:${formatCalendarDate(start)}`,

    `DTEND;TZID=${TIME_ZONE}:${formatCalendarDate(end)}`,

    `SUMMARY:${escapeIcsText(`${SHOP_NAME} - ${booking.service_name}`)}`,

    `DESCRIPTION:${escapeIcsText(description)}`,

    `LOCATION:${escapeIcsText(SHOP_LOCATION)}`,

    "END:VEVENT",
    "END:VCALENDAR",
  ].join("\r\n");

  const blob = new Blob([icsContent], {
    type: "text/calendar;charset=utf-8",
  });

  const url = URL.createObjectURL(blob);

  const link = document.createElement("a");

  link.href = url;

  link.download = `forge-and-fade-${cleanDate(booking.booking_date)}.ics`;

  document.body.appendChild(link);

  link.click();

  document.body.removeChild(link);

  URL.revokeObjectURL(url);
};
