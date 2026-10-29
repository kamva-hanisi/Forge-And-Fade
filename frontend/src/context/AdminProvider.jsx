import { useState } from "react";
import axios from "axios";
import { AdminContext } from "./AdminContext";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";

export default function AdminProvider({ children }) {
  const [session, setSession] = useState(null);
  const [busy, setBusy] = useState(false);
  const authenticate = async (mode, fields) => {
    setBusy(true);
    try {
      const { data } = await axios.post(`${apiUrl}/admin/${mode}`, fields, { timeout: 20000, headers: { Accept: "application/json" } });
      setSession(data);
    } finally { setBusy(false); }
  };

  return <AdminContext.Provider value={{ session, setSession, authenticate, busy }}>{children}</AdminContext.Provider>;
}
