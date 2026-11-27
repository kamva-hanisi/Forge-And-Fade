import { useState } from "react";
import { brandingUpload, generateBusinessBranding } from "../../utils/businessBranding";

export default function BrandingGenerator({ businessName, branding, busy, save }) {
  const [draft, setDraft] = useState(null);
  const [error, setError] = useState("");
  const [variation, setVariation] = useState(0);

  function generate() {
    try {
      setDraft(generateBusinessBranding(businessName, variation));
      setVariation((previous) => previous + 1);
      setError("");
    } catch {
      setError("Unable to generate images in this browser. You can upload images below.");
    }
  }

  async function publish() {
    const data = new FormData();
    data.set("business_name", businessName);
    data.set("logo", brandingUpload(draft.logo, "image/png"), "business-logo.png");
    data.set("cover", brandingUpload(draft.cover, "image/jpeg"), "business-cover.jpg");
    if (
      await save(
        "/management/branding/images",
        data,
        "post",
        `Branding published for ${businessName}.`,
      )
    ) {
      setDraft(null);
    }
  }

  return (
    <section className="staff-card staff-form branding-generator">
      <h2>Generate logo and cover</h2>
      <p>Create a matching logo and cover from the name {businessName}.</p>
      {error && (
        <p role="alert" className="admin-error">
          {error}
        </p>
      )}
      {draft && (
        <>
          <div className="branding-generator__preview" aria-live="polite">
            <figure>
              <figcaption>Logo preview</figcaption>
              <img src={draft.logo} alt={`Generated logo for ${businessName}`} />
            </figure>
            <figure>
              <figcaption>Cover preview</figcaption>
              <img src={draft.cover} alt={`Generated cover for ${businessName}`} />
            </figure>
          </div>
          {(branding?.logo || branding?.cover) && (
            <p>Publishing replaces any saved logo and cover images for this business.</p>
          )}
        </>
      )}
      <div className="staff-form-actions">
        <button
          className={`btn ${draft ? "btn-outline" : "btn-primary"}`}
          type="button"
          disabled={busy}
          onClick={generate}
        >
          {draft ? "Try another design" : "Generate logo and cover"}
        </button>
        {draft && (
          <>
            <button className="btn btn-primary" type="button" disabled={busy} onClick={publish}>
              Publish logo and cover
            </button>
            <button
              className="btn btn-outline"
              type="button"
              disabled={busy}
              onClick={() => {
                setDraft(null);
                setError("");
              }}
            >
              Discard preview
            </button>
          </>
        )}
      </div>
    </section>
  );
}
