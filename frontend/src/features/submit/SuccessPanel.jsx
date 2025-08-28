// src/features/submit/SuccessPanel.jsx
import Card from "@/components/cards/Card";
import { Button } from "@/components/ui/button";

export default function SuccessPanel({ onAnother, homeHref = "/" }) {
	return (
		<Card className="p-5 text-center">
			<h3 className="text-lg font-semibold">Thank you! Your drawing was submitted.</h3>
			<p className="mt-1 text-sm text-muted-foreground">
				You can submit another drawing or head back to the gallery.
			</p>

			<div className="mt-4 flex gap-3">
				<Button className="flex-1" type="button" onClick={onAnother}>
					Submit another
				</Button>
				<Button className="flex-1" variant="outline" asChild>
					<a href={homeHref}>Go to Home</a>
				</Button>
			</div>
		</Card>
	);
}
