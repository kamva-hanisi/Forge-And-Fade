import { ShieldCheck } from "lucide-react";

export default function AdminAccessIntro({ title, accent, children }) {
  return (
    <div className="admin-access__intro">
      <span className="section-label">Forge &amp; Fade · Administration</span>
      <h1 className="section-title">
        {title}
        <br /> <span>{accent}</span>
      </h1>
      <p>{children}</p>
      <div className="admin-access__photos" aria-hidden="true">
        <div className="admin-access__photo-shop" />
        <div className="admin-access__photo-tools" />
      </div>
      <span className="admin-access__staff">
        <ShieldCheck size={18} aria-hidden="true" /> For business owners and authorized staff
      </span>
    </div>
  );
}
