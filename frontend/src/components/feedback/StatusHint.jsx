// src/components/feedback/StatusHint.jsx
import { cn } from "@/lib/utils";
import {
  Info as InfoIcon,
  AlertTriangle,
  XCircle,
  CheckCircle2,
  Loader2,
} from "lucide-react";

const VARIANTS = {
  info: {
    icon: InfoIcon,
    base: "text-blue-700",
    chip: "bg-blue-50 border-blue-200",
  },
  warning: {
    icon: AlertTriangle,
    base: "text-amber-800",
    chip: "bg-amber-50 border-amber-200",
  },
  error: {
    icon: XCircle,
    base: "text-red-700",
    chip: "bg-red-50 border-red-200",
  },
  success: {
    icon: CheckCircle2,
    base: "text-green-700",
    chip: "bg-green-50 border-green-200",
  },
  neutral: {
    icon: InfoIcon,
    base: "text-slate-700",
    chip: "bg-slate-50 border-slate-200",
  },
  loading: {
    icon: Loader2,
    base: "text-slate-700",
    chip: "bg-slate-50 border-slate-200",
    spin: true,
  },
};

/**
 * StatusHint
 * ---------
 * Tiny, inline alert/hint row with icon + short text.
 *
 * Props:
 * - variant: "info" | "warning" | "error" | "success" | "neutral" | "loading"
 * - children: ReactNode (the message)
 * - className?: string
 * - icon?: React.ComponentType (override the default icon)
 * - subtle?: boolean  // if true, removes chip bg/border, keeps only text + icon
 * - ariaLive?: "polite" | "assertive" | "off" (default "polite")
 */
export default function StatusHint({
  variant = "neutral",
  children,
  className,
  icon: IconOverride,
  subtle = false,
  ariaLive = "polite",
}) {
  const theme = VARIANTS[variant] || VARIANTS.neutral;
  const Icon = IconOverride || theme.icon;

  return (
    <div
      role="status"
      aria-live={ariaLive}
      className={cn(
        "inline-flex items-center gap-1.5 rounded-md text-xs",
        subtle
          ? theme.base
          : cn(
              "px-2 py-1 border",
              theme.chip,
              // keep text readable inside chip
              theme.base.replace("text-", "text-")
            ),
        className
      )}
    >
      <Icon
        className={cn(
          "h-3.5 w-3.5 shrink-0",
          theme.spin && "animate-spin"
        )}
        aria-hidden="true"
      />
      <span className="leading-none">{children}</span>
    </div>
  );
}
