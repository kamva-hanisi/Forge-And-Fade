import { createContext, useContext, useEffect, useState } from "react";
import axios from "axios";
import { services as descriptions } from "../data/services";

export const PublicCatalogContext = createContext(null);

export function usePublicCatalog() {
  return useContext(PublicCatalogContext);
}

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
const refreshInterval = 15000;

export default function useShopServices(requestedShop, { enabled = true, live = true } = {}) {
  const [state, setState] = useState(null);
  const [retryCount, setRetryCount] = useState(0);

  useEffect(() => {
    if (!enabled) return;
    const controller = new AbortController();
    let pending = false;

    async function load() {
      if (pending || controller.signal.aborted) return;
      pending = true;
      try {
        const { data } = await axios.get(`${apiUrl}/bookings/catalog`, {
          params: requestedShop ? { shop: requestedShop } : undefined,
          signal: controller.signal,
          timeout: 20000,
        });
        if (controller.signal.aborted) return;
        const shop = data.shop.slug;
        setState({
          requestedShop,
          shop,
          profile: data.shop,
          hours: data.hours,
          barbers: data.barbers,
          branding: data.branding,
          status: data.booking_status,
          error: "",
          services: data.services.map((item) => ({
            ...item,
            description:
              item.description !== undefined
                ? item.description
                : shop === "forge-and-fade"
                  ? descriptions.find(
                      (description) => description.id === item.id && description.name === item.name,
                    )?.description
                  : undefined,
          })),
        });
      } catch (error) {
        if (controller.signal.aborted) return;
        const missing = error.response?.status === 404;
        setState((previous) => {
          if (!missing && previous?.requestedShop === requestedShop && previous.shop) {
            return previous;
          }

          return {
            requestedShop,
            error: missing
              ? "This business is not available."
              : "Business details are temporarily unavailable.",
          };
        });
      } finally {
        pending = false;
      }
    }

    function refreshVisiblePage() {
      if (document.visibilityState === "visible") load();
    }

    load();
    const interval =
      live && !requestedShop ? window.setInterval(refreshVisiblePage, refreshInterval) : null;
    if (interval !== null) document.addEventListener("visibilitychange", refreshVisiblePage);

    return () => {
      controller.abort();
      if (interval !== null) {
        window.clearInterval(interval);
        document.removeEventListener("visibilitychange", refreshVisiblePage);
      }
    };
  }, [enabled, live, requestedShop, retryCount]);

  const current = enabled && state?.requestedShop === requestedShop ? state : null;
  return {
    services: current?.services || [],
    error: current?.error || "",
    loading: enabled && !current,
    shop: current?.shop,
    profile: current?.profile,
    hours: current?.hours,
    barbers: current?.barbers || [],
    branding: current?.branding,
    status: current?.status,
    retry: () => setRetryCount((value) => value + 1),
  };
}
