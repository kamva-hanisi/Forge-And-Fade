import test from "node:test";
import assert from "node:assert/strict";
import { resolveShopSlug, shopPath } from "../src/utils/shopLinks.js";

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
