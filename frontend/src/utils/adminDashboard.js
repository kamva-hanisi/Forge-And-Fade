export function adminDashboard(role) {
  if (["owner", "website_owner", "platform_admin"].includes(role)) {
    return {
      kind: "owner",
      path: "/admin/owner",
      title: role === "owner" ? "Business Owner dashboard" : "Business dashboard",
    };
  }

  return { kind: "staff", path: "/admin/staff", title: "Staff dashboard" };
}

export const rolesPath = "/admin/settings/roles";

export function adminLanding(user) {
  if (user.permissions.includes("bookings.read")) return adminDashboard(user.role).path;
  if (user.permissions.includes("payments.read")) return "/admin/payments";
  return user.platform_access ? "/admin/platform" : rolesPath;
}

export function accessRoleLabel(role) {
  return (
    {
      owner: "Website Owner",
      super_admin: "Super Admin",
      admin: "Admin",
      shop_owner: "Business Owner",
      manager: "Manager",
      barber: "Barber",
      platform_admin: "Legacy platform administrator",
      revoked: "Revoked",
    }[role] || "No website role"
  );
}
