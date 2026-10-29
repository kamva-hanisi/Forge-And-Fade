import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { calendarIcs, googleCalendarUrl } from "../src/utils/calendar.js";

const booking = {
  id: 42,
  reference: "FF-EXAMPLE123456789",
  service_name: "Skin Fade",
  barber_name: "Liam Carter",
  booking_date: "2026-11-03",
  booking_time: "10:00:00",
  duration: 50,
  timezone: "Africa/Johannesburg",
  starts_at: "2026-11-03T10:00:00+02:00",
  ends_at: "2026-11-03T10:50:00+02:00",
};
const now = new Date("2026-10-29T10:00:00Z");

test("calendar exports use the booked shop instead of the platform's default location", () => {
  const scoped = { ...booking, shop: { name: "Other Shop", address: "Other Location" } };
  const params = new URL(googleCalendarUrl(scoped)).searchParams;
  assert.equal(params.get("text"), "Other Shop - Skin Fade");
  assert.equal(params.get("location"), "Other Location");
  assert.match(calendarIcs(scoped, now), /LOCATION:Other Location/);
});

test("calendar exports represent the server's exact appointment instant", () => {
  const params = new URL(googleCalendarUrl(booking)).searchParams;
  assert.equal(params.get("dates"), "20261103T080000Z/20261103T085000Z");
  assert.equal(params.get("ctz"), "Africa/Johannesburg");
  const ics = calendarIcs(booking, now);
  assert.match(ics, /DTSTART:20261103T080000Z/);
  assert.match(ics, /DTEND:20261103T085000Z/);
  assert.match(ics, /UID:FF-EXAMPLE123456789@forgeandfade.co.za/);
  assert.equal(ics, calendarIcs(booking, now));
});

test("exports are identical in UTC, New York, Auckland and Kolkata", () => {
  const moduleUrl = new URL("../src/utils/calendar.js", import.meta.url).href;
  const script = `import { calendarIcs, googleCalendarUrl } from ${JSON.stringify(moduleUrl)};
    const booking = ${JSON.stringify(booking)};
    console.log(JSON.stringify([googleCalendarUrl(booking), calendarIcs(booking, new Date(${JSON.stringify(now.toISOString())}))]));`;
  const outputs = ["UTC", "America/New_York", "Pacific/Auckland", "Asia/Kolkata"].map(
    (timezone) => {
      const result = spawnSync(process.execPath, ["--input-type=module", "-e", script], {
        env: { ...process.env, TZ: timezone },
        encoding: "utf8",
      });
      assert.equal(result.status, 0, result.stderr);
      return result.stdout;
    },
  );
  for (const output of outputs) assert.equal(output, outputs[0]);
});

test("legacy South Africa bookings and end dates crossing midnight stay correct", () => {
  const legacy = {
    ...booking,
    starts_at: undefined,
    ends_at: undefined,
    booking_time: "23:40",
    duration: 90,
  };
  const params = new URL(googleCalendarUrl(legacy)).searchParams;
  assert.equal(params.get("dates"), "20261103T214000Z/20261103T231000Z");
});

test("ICS text cannot inject event fields and long UTF-8 lines are folded", () => {
  const ics = calendarIcs(
    { ...booking, service_name: "Cut\r\nATTENDEE:attacker@example.com,;" + "✂️".repeat(60) },
    now,
  );
  assert.equal(ics.includes("\r\nATTENDEE:"), false);
  assert.equal(ics.includes("\\nATTENDEE:"), true);
  for (const line of ics.split("\r\n")) assert.ok(new TextEncoder().encode(line).length <= 75);
});

test("invalid appointment dates are rejected rather than exported", () => {
  assert.throws(() => calendarIcs({ ...booking, starts_at: "invalid" }, now));
  assert.throws(() => googleCalendarUrl({ ...booking, ends_at: booking.starts_at }));
});
