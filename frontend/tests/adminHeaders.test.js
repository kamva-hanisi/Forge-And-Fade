import test from "node:test";
import assert from "node:assert/strict";
import { adminHeaders, platformHeaders } from "../src/utils/adminHeaders.js";

test("admin requests remain pinned to the shop selected at sign-in", () => {
  assert.deepEqual(adminHeaders({ token: "test-only", user: { shop: { id: 7 } } }), {
    Authorization: "Bearer test-only",
    Accept: "application/json",
    "X-Shop-Id": "7",
  });
});

test("a missing shop identity is not replaced by an invented shop header", () => {
  assert.equal(adminHeaders({ token: "test-only", user: { shop: null } })["X-Shop-Id"], undefined);
});

test("platform overview requests use authentication without a selected-shop scope", () => {
  assert.deepEqual(platformHeaders({ token: "platform-test", user: { shop: { id: 7 } } }), {
    Authorization: "Bearer platform-test",
    Accept: "application/json",
  });
});
