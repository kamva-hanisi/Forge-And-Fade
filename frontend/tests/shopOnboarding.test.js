import test from "node:test";
import assert from "node:assert/strict";
import { catalogReadiness } from "../src/utils/shopOnboarding.js";

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
