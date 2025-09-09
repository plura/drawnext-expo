// src/components/feedback/AlertDialog.jsx
import {
  AlertDialog as ShadcnAlertDialog,
  AlertDialogTrigger,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
  AlertDialogAction,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";

/**
 * ConfirmDialog
 * -------------
 * Props:
 * - title: string
 * - description?: string
 * - confirmText?: string (default: "Confirm")
 * - cancelText?: string (default: "Cancel")
 * - onConfirm: () => (void|Promise<void>)
 * - trigger?: ReactNode (custom trigger element)
 * - triggerText?: string (label for generated trigger button)
 * - variant?: Button variant for trigger (default: "destructive")
 * - size?: Button size for trigger (default: "default")
 * - actionClassName?: string (extra classes for confirm action)
 */
export default function ConfirmDialog({
  title,
  description,
  confirmText = "Confirm",
  cancelText = "Cancel",
  onConfirm,
  trigger,
  triggerText,
  variant = "destructive",
  size = "default",
  actionClassName,
}) {
  return (
    <ShadcnAlertDialog>
      <AlertDialogTrigger asChild>
        {trigger ? (
          trigger
        ) : (
          <Button variant={variant} size={size} type="button">
            {triggerText || confirmText}
          </Button>
        )}
      </AlertDialogTrigger>

      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          {description && (
            <AlertDialogDescription>{description}</AlertDialogDescription>
          )}
        </AlertDialogHeader>

        <AlertDialogFooter>
          <AlertDialogCancel>{cancelText}</AlertDialogCancel>
          <AlertDialogAction
            className={actionClassName}
            onClick={async () => {
              try {
                await onConfirm?.();
              } catch (e) {
                // leave handling to caller if needed; no-op here
                // console.error(e);
              }
            }}
          >
            {confirmText}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </ShadcnAlertDialog>
  );
}
