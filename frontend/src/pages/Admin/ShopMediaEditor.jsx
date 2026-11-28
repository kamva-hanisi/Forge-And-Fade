import { useEffect, useState } from "react";
import { shopMediaUrl } from "../../utils/shopMedia";

function PrivatePreview({ client, image, caption }) {
  const [source, setSource] = useState("");
  useEffect(() => {
    const controller = new AbortController();
    let url;
    client
      .get(`/management/media/${encodeURIComponent(image)}`, {
        responseType: "blob",
        signal: controller.signal,
      })
      .then(({ data }) => {
        if (controller.signal.aborted) return;
        url = URL.createObjectURL(data);
        setSource(url);
      })
      .catch(() => {
        if (!controller.signal.aborted) setSource(null);
      });
    return () => {
      controller.abort();
      if (url) URL.revokeObjectURL(url);
    };
  }, [client, image]);
  return source ? (
    <img src={source} alt={caption || ""} />
  ) : (
    <p>{source === null ? "Preview unavailable" : "Loading preview…"}</p>
  );
}

export function MediaEditor({ title, target, barberId, images, slug, busy, save, previewClient }) {
  const portfolio = target === "portfolio";
  return (
    <section className="staff-card staff-form shop-media-editor">
      <h2>{title}</h2>
      {images.length > 0 && (
        <ul className="shop-media-editor__images" data-target={target}>
          {images.map(({ image, caption }) => (
            <li key={image}>
              {previewClient ? (
                <PrivatePreview client={previewClient} image={image} caption={caption} />
              ) : (
                <img src={shopMediaUrl(slug, image)} alt={caption || ""} loading="lazy" />
              )}
              {caption && <p>{caption}</p>}
              <button
                className="btn btn-outline"
                type="button"
                disabled={busy}
                onClick={() =>
                  save("/management/media", { target, barber_id: barberId, remove: image }, "post")
                }
              >
                Remove {portfolio ? "photo" : target === "logo" ? "logo" : "cover"}
              </button>
            </li>
          ))}
        </ul>
      )}
      {(!portfolio || images.length < 6) && (
        <form
          className="staff-form"
          onSubmit={(event) => {
            event.preventDefault();
            const data = new FormData(event.currentTarget);
            data.set("target", target);
            if (portfolio) data.set("barber_id", barberId);
            save("/management/media", data, "post");
          }}
        >
          <label>
            {portfolio ? "Haircut photo" : title}
            <input
              name="photo"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              required
              disabled={busy}
            />
          </label>
          {portfolio && (
            <label>
              Caption (optional)
              <input name="caption" maxLength={80} disabled={busy} />
            </label>
          )}
          <button className="btn btn-primary" disabled={busy}>
            {portfolio ? "Add photo" : images.length ? "Replace image" : "Upload image"}
          </button>
        </form>
      )}
    </section>
  );
}

export function BrandingEditor({ branding, busy, save }) {
  const [accent, setAccent] = useState(branding.accent || "#e0b98d");
  return (
    <form
      className="staff-card staff-form"
      onSubmit={(event) => {
        event.preventDefault();
        save("/management/branding", { accent });
      }}
    >
      <h2>Accent colour</h2>
      <label>
        Business accent colour
        <input
          type="color"
          value={accent}
          onChange={(event) => setAccent(event.target.value)}
          disabled={busy}
        />
      </label>
      <div className="staff-form-actions">
        <button className="btn btn-primary" disabled={busy}>
          Save accent colour
        </button>
        <button
          className="btn btn-outline"
          type="button"
          disabled={busy || !branding.accent}
          onClick={() => save("/management/branding", { accent: null })}
        >
          Use default
        </button>
      </div>
    </form>
  );
}
