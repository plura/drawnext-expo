// src/components/form/Select.jsx
import * as React from "react";
import {
  Select as RadixSelect,
  SelectTrigger,
  SelectContent,
  SelectItem,
  SelectValue,
  SelectGroup,
} from "@/components/ui/select";
import { Label } from "@/components/ui/label";

/**
 * Props:
 * - id?: string
 * - label?: string
 * - value: string | undefined ('' means “no selection” → shows placeholder)
 * - onChange: (val: string) => void
 * - options: Array<{ value: string; label: string; disabled?: boolean }>
 * - placeholder?: string
 * - disabled?: boolean
 * - className?: string
 */
export default function Select({
  id,
  label,
  value,
  onChange,
  options = [],
  placeholder = "Select…",
  disabled = false,
  className,
}) {
  // IMPORTANT: Filter out any empty-string items (Radix requires non-empty)
  const safeOptions = React.useMemo(
    () => options.filter((o) => typeof o.value === "string" && o.value.length > 0),
    [options]
  );

  return (
    <div className={className}>
      {label && (
        <Label htmlFor={id} className="mb-1 block">
          {label}
        </Label>
      )}
      <RadixSelect value={value ?? ""} onValueChange={onChange} disabled={disabled}>
        <SelectTrigger id={id}>
          <SelectValue placeholder={placeholder} />
        </SelectTrigger>
        <SelectContent>
          <SelectGroup>
            {safeOptions.map((opt) => (
              <SelectItem key={opt.value} value={opt.value} disabled={opt.disabled}>
                {opt.label}
              </SelectItem>
            ))}
          </SelectGroup>
        </SelectContent>
      </RadixSelect>
    </div>
  );
}
