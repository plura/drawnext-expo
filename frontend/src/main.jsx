//src/main.jsx
import { createRoot } from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import App from "./App";
import { ConfigProvider } from "@/app/ConfigProvider";
import "./index.css";

createRoot(document.getElementById("root")).render(
  <ConfigProvider>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </ConfigProvider>
);
