import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { HashRouter } from "react-router-dom";

import App from "./App.jsx";
import AdminProvider from "./context/AdminProvider";
import "./styles/global.scss";

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <HashRouter>
      <AdminProvider>
        <App />
      </AdminProvider>
    </HashRouter>
  </StrictMode>,
);
