const SHOP_NAME = "Forge & Fade";
const SHOP_LOCATION = "Cape Town, South Africa";
const TIME_ZONE = "Africa/Johannesburg";

const cleanDate = (date) => String(date).split("T")[0];
const formatUtcDate = (date) =>
  date
    .toISOString()
    .replace(/[-:]/g, "")
    .replace(/\.\d{3}/, "");

function appointmentDates(booking) {
  const start = booking.starts_at
    ? new Date(booking.starts_at)
    : new Date(
        cleanDate(booking.booking_date) +
          "T" +
          String(booking.booking_time).slice(0, 5) +
          ":00+02:00",
      );
  const end = booking.ends_at
    ? new Date(booking.ends_at)
    : new Date(start.getTime() + Number(booking.duration) * 60000);
  if (!Number.isFinite(start.getTime()) || !Number.isFinite(end.getTime()) || end <= start) {
    throw new Error("This appointment has invalid calendar dates.");
  }
  return { start, end };
}

const escapeIcsText = (value = "") =>
  String(value)
    .replace(/\\/g, "\\\\")
    .replace(/\r\n|\r|\n/g, "\\n")
    .replace(/,/g, "\\,")
    .replace(/;/g, "\\;");

function foldLine(line) {
  const encoder = new TextEncoder();
  const parts = [];
  let current = "";
  for (const character of line) {
    if (encoder.encode(current + character).length > 75) {
      parts.push(current);
      current = " ";
    }
    current += character;
  }
  parts.push(current);
  return parts.join("\r\n");
}

export function googleCalendarUrl(booking) {
  const { start, end } = appointmentDates(booking);
  const params = new URLSearchParams({
    action: "TEMPLATE",
    text: (booking.shop?.name || SHOP_NAME) + " - " + booking.service_name,
    dates: formatUtcDate(start) + "/" + formatUtcDate(end),
    details:
      "Appointment with " +
      booking.barber_name +
      ". Service: " +
      booking.service_name +
      ". Reference: " +
      (booking.reference || booking.id) +
      ".",
    location: booking.shop ? booking.shop.address || "" : SHOP_LOCATION,
    ctz: booking.timezone || TIME_ZONE,
  });
  return "https://calendar.google.com/calendar/render?" + params.toString();
}

export function calendarIcs(booking, now = new Date()) {
  const { start, end } = appointmentDates(booking);
  const identifier = booking.reference || booking.id;
  const description =
    "Appointment with " +
    booking.barber_name +
    ". Service: " +
    booking.service_name +
    ". Reference: " +
    identifier +
    ".";
  return (
    [
      "BEGIN:VCALENDAR",
      "VERSION:2.0",
      "CALSCALE:GREGORIAN",
      "METHOD:PUBLISH",
      "PRODID:-//Forge & Fade//Booking Calendar//EN",
      "BEGIN:VEVENT",
      "UID:" + identifier + "@forgeandfade.co.za",
      "DTSTAMP:" + formatUtcDate(now),
      "DTSTART:" + formatUtcDate(start),
      "DTEND:" + formatUtcDate(end),
      "SUMMARY:" + escapeIcsText((booking.shop?.name || SHOP_NAME) + " - " + booking.service_name),
      "DESCRIPTION:" + escapeIcsText(description),
      "LOCATION:" + escapeIcsText(booking.shop ? booking.shop.address || "" : SHOP_LOCATION),
      "END:VEVENT",
      "END:VCALENDAR",
    ]
      .map(foldLine)
      .join("\r\n") + "\r\n"
  );
}

export const openGoogleCalendar = (booking) => {
  window.open(googleCalendarUrl(booking), "_blank", "noopener,noreferrer");
};

export const downloadAppleCalendar = (booking) => {
  const blob = new Blob([calendarIcs(booking)], { type: "text/calendar;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download =
    "forge-and-fade-" + (booking.reference || cleanDate(booking.booking_date)) + ".ics";
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
};
