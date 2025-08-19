// src/components/AppAlert.jsx
import { Alert, AlertTitle, AlertDescription } from "@/components/ui/alert"

export default function AppAlert({
	variant = "default",
	title,
	children,
	className = "",
}) {
	return (
		<div className={`mt-3 ${className}`}>
			<Alert variant={variant}>
				{title && <AlertTitle>{title}</AlertTitle>}
				{children && <AlertDescription>{children}</AlertDescription>}
			</Alert>
		</div>
	)
}
