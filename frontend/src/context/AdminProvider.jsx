import { useCallback, useEffect, useState } from "react";
import { useLocation } from "react-router-dom";
import axios from "axios";
import { AdminContext } from "./AdminContext";
import { adminHeaders } from "../utils/adminHeaders";

const apiUrl = import.meta.env.VITE_API_URL || "http://127.0.0.1:8000/api";
const storageKey = "forge-and-fade.admin-session";

function readStoredSession() {
  try {
    const saved = JSON.parse(window.sessionStorage.getItem(storageKey));
    return typeof saved?.token === "string" && saved.token ? saved : null;
  } catch {
    return null;
  }
}

function storeSession(session) {
  try {
    if (session) {
      window.sessionStorage.setItem(
        storageKey,
        JSON.stringify({ token: session.token, shopId: session.user.shop?.id }),
      );
    } else {
      window.sessionStorage.removeItem(storageKey);
    }
  } catch {
    // Sign-in still works in memory when browser storage is disabled.
  }
}

export default function AdminProvider({ children }) {
  const { pathname } = useLocation();
  const adminPage = pathname === "/admin" || pathname.startsWith("/admin/");
  const [session, updateSession] = useState(null);
  const [stored, setStored] = useState(readStoredSession);
  const [restoreFailed, setRestoreFailed] = useState(false);
  const [restoreAttempt, setRestoreAttempt] = useState(0);
  const [busy, setBusy] = useState(false);
  const setSession = useCallback((next) => {
    if (typeof next !== "function") {
      storeSession(next);
      setStored(null);
    }
    updateSession(next);
  }, []);

  useEffect(() => {
    if (!adminPage || !stored) return;

    const controller = new AbortController();
    const restore = (shopId) =>
      axios.get(`${apiUrl}/admin/session`, {
        headers: adminHeaders({ token: stored.token, user: { shop: { id: shopId } } }),
        signal: controller.signal,
        timeout: 20000,
      });
    restore(stored.shopId)
      .catch((err) => {
        if (stored.shopId && [403, 404].includes(err.response?.status)) return restore();
        throw err;
      })
      .then(({ data }) => setSession({ token: stored.token, user: data.user }))
      .catch((err) => {
        if (controller.signal.aborted) return;
        if ([401, 403, 404].includes(err.response?.status)) setSession(null);
        else setRestoreFailed(true);
      });
    return () => controller.abort();
  }, [adminPage, stored, restoreAttempt, setSession]);

  const authenticate = async (mode, fields) => {
    setBusy(true);
    try {
      const { data } = await axios.post(`${apiUrl}/admin/${mode}`, fields, {
        timeout: 20000,
        headers: { Accept: "application/json" },
      });
      setSession(data);
    } finally {
      setBusy(false);
    }
  };

  if (adminPage && stored) {
    return (
      <main className="container staff-page">
        {restoreFailed ? (
          <>
            <p role="alert">Unable to check your admin session. Check your connection and retry.</p>
            <button
              className="btn btn-outline"
              type="button"
              onClick={() => {
                setRestoreFailed(false);
                setRestoreAttempt((attempt) => attempt + 1);
              }}
            >
              Retry
            </button>
          </>
        ) : (
          <p role="status">Checking your admin session…</p>
        )}
      </main>
    );
  }

  return (
    <AdminContext.Provider value={{ session, setSession, authenticate, busy }}>
      {children}
    </AdminContext.Provider>
  );
}
