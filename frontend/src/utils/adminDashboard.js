export function adminDashboard(role) {
  if (role === "owner" || role === "platform_admin") {
    return {
      kind: "owner",
      path: "/admin/owner",
      title: role === "platform_admin" ? "Shop dashboard" : "Owner dashboard",
    };
  }

  return { kind: "staff", path: "/admin/staff", title: "Staff dashboard" };
}
