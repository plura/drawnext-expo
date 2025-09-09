// src/components/wall/ExhibitionWallBar.jsx
import AppLogoBrand from "@/assets/app_logo_brand.svg?react";
import AppLogoDev from "@/assets/app_logo_dev.svg?react";
/* import AppQRCodeSubmit from "@/assets/app_qrcode_submit.svg?react"; */

/**
 * Exhibition bar for branding & QR info.
 *
 * Props:
 * - row (boolean, default true): when true → horizontal bar; when false → vertical sidebar
 */
export default function ExhibitionWallBar({ row = true }) {
  const dir = row ? "flex-row" : "flex-col";
  const pad = row ? "px-3 py-3" : "px-3 py-4";

  return (
    <div className={`shrink-0 ${pad}`} role="presentation" aria-hidden="true">
      <div className={`flex ${dir} items-center justify-between`}>
        {/* Left group: logos */}
        <div className={`flex ${row ? "flex-row" : "flex-col"} items-center gap-10`}>
          <AppLogoBrand className="h-4 w-auto" />
          <AppLogoDev className="h-4 w-auto" />
        </div>

        {/* Middle: URL text */}
{/*         <div className={`text-md font-medium text-foreground ${row ? "" : "mt-3"}`}>
          What would the future be like in 100 years?
        </div> */}

        {/* Middle: URL text */}
        <div className={`text-xs font-medium text-foreground ${row ? "" : "mt-3"}`}>
          https://osaka.toyno.com/explore
        </div>

        {/* Right: “QR code” placeholder (using same SVG for now) */}
      {/*   <div className={`${row ? "" : "mt-3"}`}>
          <AppQRCodeSubmit className="h-4 w-auto" />
        </div> */}
      </div>
    </div>
  );
}
