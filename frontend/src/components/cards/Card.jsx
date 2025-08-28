// src/components/card/Card.jsx
import clsx from "clsx";

export default function Card({ children, className = "", ...props }) {
	return (
		<div
			className={clsx("p-3 rounded-lg border bg-white", className)}
			{...props}
		>
			{children}
		</div>
	);
}
