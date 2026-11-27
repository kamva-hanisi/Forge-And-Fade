import test from "node:test";
import assert from "node:assert/strict";
import { businessIdentifier, catalogReadiness } from "../src/utils/shopOnboarding.js";

test("business identifiers normalize names as they are entered", () => {
  assert.equal(businessIdentifier("  Café & Cuts 24! ", 2026), "ca-2026-ts");
  assert.equal(businessIdentifier("Café ", 2027), "ca-2027-fe");
  assert.equal(businessIdentifier("", 2027), "");
});

test("short names and names without Latin letters still generate valid identifiers", () => {
  assert.equal(businessIdentifier("A", 2026), "ax-2026-ax");
  assert.equal(businessIdentifier("AB", 2026), "ab-2026-ab");
  assert.equal(businessIdentifier("東京", 2026), "xx-2026-xx");
  assert.equal(businessIdentifier("A".repeat(100), 2026), "aa-2026-aa");
});

test("a shop needs both an active service and an active barber", () => {
  assert.deepEqual(catalogReadiness(), { serviceCount: 0, barberCount: 0, ready: false });
  assert.equal(catalogReadiness([{ active: true }], []).ready, false);
  assert.equal(catalogReadiness([], [{ active: true }]).ready, false);
  assert.equal(catalogReadiness([{ active: true }], [{ active: true }]).ready, true);
});

test("disabled catalog items never make onboarding ready", () => {
  assert.deepEqual(catalogReadiness([{ active: false }], [{ active: true }]), {
    serviceCount: 0,
    barberCount: 1,
    ready: false,
  });
  assert.equal(catalogReadiness([{}], [{ active: false }]).ready, false);
});
