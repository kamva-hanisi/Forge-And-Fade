import { useEffect, useRef, useState } from "react";
import { useLocation } from "react-router-dom";
import axios from "axios";
import { useAdmin } from "../../context/AdminContext";
import { formError } from "../../utils/formError";
import ScheduleEditor from "./ScheduleEditor";

export default function BusinessHoursSettings({ client }) {
  const { setSession } = useAdmin();
  const { hash } = useLocation();
  const section = useRef(null);
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [saving, setSaving] = useState(false);
  const [refresh, setRefresh] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    client
      .get("/management/business-hours", { signal: controller.signal })
      .then(({ data }) => {
        setData(data);
        setError("");
      })
      .catch((failure) => {
        if (axios.isCancel(failure)) return;
        if (failure.response?.status === 401) setSession(null);
        else setError(formError(failure));
      });
    return () => controller.abort();
  }, [client, refresh, setSession]);

  const loaded = data !== null;
  useEffect(() => {
    if (hash === "#business-hours") section.current?.scrollIntoView({ block: "start" });
  }, [hash, loaded]);

  async function save(url, schedule) {
    if (saving) return;
    setSaving(true);
    setError("");
    setNotice("");
    try {
      const { data: updated } = await client.put(url, { ...schedule, version: data.version });
      setData(updated);
      setNotice("Business opening hours saved.");
    } catch (failure) {
      if (failure.response?.status === 401) setSession(null);
      else setError(formError(failure));
    } finally {
      setSaving(false);
    }
  }

  return (
    <section id="business-hours" ref={section} className="staff-card staff-invitations">
      <h2>Business opening hours</h2>
      {data?.shop_status === "suspended" && (
        <p>Reactivate this business to edit its opening hours.</p>
      )}
      {error && (
        <>
          <p role="alert" className="admin-error">
            {error}
          </p>
          <button
            className="btn btn-outline"
            type="button"
            onClick={() => setRefresh((value) => value + 1)}
          >
            Refresh opening hours
          </button>
        </>
      )}
      {notice && (
        <p role="status" className="admin-message">
          {notice}
        </p>
      )}
      {!data && !error && <p role="status">Loading opening hours…</p>}
      {data && (
        <ScheduleEditor
          key={data.version}
          business
          data={data}
          busy={saving || data.shop_status === "suspended"}
          save={save}
        />
      )}
    </section>
  );
}
