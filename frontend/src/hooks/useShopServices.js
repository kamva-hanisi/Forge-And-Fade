import { useEffect, useState } from "react";
import { useSearchParams } from "react-router-dom";
import axios from "axios";
import { services as descriptions } from "../data/services";

export default function useShopServices(fixedShop) {
  const [params] = useSearchParams();
  const shop = fixedShop || params.get("shop") || "forge-and-fade";
  const [state, setState] = useState({ shop: "", services: [], error: "" });
  const [refresh, setRefresh] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    axios
      .get(`${import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api"}/bookings/catalog`, {
        params: { shop },
        signal: controller.signal,
        timeout: 20000,
      })
      .then(({ data }) =>
        setState({
          shop,
          profile: data.shop,
          hours: data.hours,
          barbers: data.barbers,
          branding: data.branding,
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
        }),
      )
      .catch((err) => {
        if (!axios.isCancel(err))
          setState({
            shop,
            services: [],
            error: "Services are temporarily unavailable. Please retry.",
          });
      });
    return () => controller.abort();
  }, [shop, refresh]);
  const current = state.shop === shop ? state : null;
  return {
    services: current?.services || [],
    error: current?.error || "",
    loading: !current,
    shop,
    profile: current?.profile,
    hours: current?.hours,
    barbers: current?.barbers || [],
    branding: current?.branding,
    retry: () => {
      setState({ shop: "", services: [], error: "" });
      setRefresh((value) => value + 1);
    },
  };
}
