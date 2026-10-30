export function adminHeaders(session) {
  return {
    Authorization: `Bearer ${session.token}`,
    Accept: "application/json",
    ...(session.user.shop?.id ? { "X-Shop-Id": String(session.user.shop.id) } : {}),
  };
}
