import test from "node:test";
import assert from "node:assert/strict";
import {
  bookingLink,
  initialBookingSelection,
  resolveShopSlug,
  shopPath,
} from "../src/utils/shopLinks.js";

test("public shop links keep booking and management scoped to the same slug", () => {
  assert.equal(shopPath("cape-cuts"), "/shops/cape-cuts/book");
  assert.equal(shopPath("cape-cuts", true), "/shops/cape-cuts/manage");
  assert.equal(shopPath("bad/slug?shop=another"), "/shops/bad%2Fslug%3Fshop%3Danother/book");
});

test("a dedicated shop route cannot be overridden by a shop query parameter", () => {
  assert.equal(resolveShopSlug("cape-cuts", "other-shop"), "cape-cuts");
  assert.equal(resolveShopSlug(undefined, "other-shop"), "other-shop");
  assert.equal(resolveShopSlug(undefined, null), "forge-and-fade");
});

test("a suggested time link preselects shop catalog choices and preserves date and time", () => {
  const catalog = { services: [{ id: 2 }], barbers: [{ id: 3 }] };
  const href = bookingLink("cape-cuts", {
    service: 2,
    barber: "any",
    date: "2026-11-26",
    time: "08:50",
  });
  const [path, query] = href.split("?");
  assert.equal(path, "/shops/cape-cuts/book");
  const params = new URLSearchParams(query);
  assert.deepEqual(initialBookingSelection(params, catalog), {
    service_id: "2",
    barber_id: "any",
    booking_date: "2026-11-26",
    booking_time: "08:50",
  });
  params.set("date", "2026-11-31");
  assert.equal(initialBookingSelection(params, catalog).booking_time, "");
  params.set("date", "2026-11-26");
  params.set("barber", "12");
  assert.equal(initialBookingSelection(params, catalog).booking_time, "");
});
