const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

export function shopMediaUrl(slug, image) {
  return `${apiUrl}/shop-media/${encodeURIComponent(slug)}/${encodeURIComponent(image)}`;
}
