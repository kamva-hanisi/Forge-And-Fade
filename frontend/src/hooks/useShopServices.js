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
          error: "",
          services: data.services.map((item) => ({
            ...item,
            description:
              descriptions.find(
                (description) => description.id === item.id && description.name === item.name,
              )?.description || "Choose your preferred barber and an available appointment time.",
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
  return {
    services: state.shop === shop ? state.services : [],
    error: state.shop === shop ? state.error : "",
    loading: state.shop !== shop,
    shop,
    profile: state.shop === shop ? state.profile : null,
    retry: () => {
      setState({ shop: "", services: [], error: "" });
      setRefresh((value) => value + 1);
    },
  };
}
