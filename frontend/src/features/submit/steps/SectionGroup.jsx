// src/features/submit/steps/SectionGroup.jsx

export default function SectionGroup({
	section,
	label,
	isPrimary,
	maxPages,
	primaryPage,
	neighborPage,
	onSelectPrimary,
	onChangePrimaryPage,
	onChangeNeighborPage,
}) {
	function handleContainerClick(e) {
		if (e.target.closest("[data-no-select='true']")) return;
		onSelectPrimary(section.id);
	}

	// Build page options once
	const pageOptions = Array.from({ length: maxPages }, (_, i) => i + 1);

	return (
		<div
			onClick={handleContainerClick}
			className={`flex items-center gap-3 rounded-xl border p-3 transition cursor-pointer
				${
					isPrimary
						? "bg-primary border-primary text-primary-foreground"
						: "bg-white hover:bg-gray-50 text-gray-900"
				}
			`}
			aria-pressed={!!isPrimary}
		>
			{/* Left: section label */}
			<div className="min-w-0 flex-1">
				<div className="truncate text-base font-medium">{label}</div>
				<p
					className={`mt-0.5 text-xs ${
						isPrimary ? "text-blue-100" : "text-muted-foreground"
					}`}
				>
					{typeof isPrimary === "boolean"
						? isPrimary
							? "Selected section"
							: "Tap to select this as your section"
						: "Tap to select this as your section"}
				</p>
			</div>

			{/* Right: page selects */}
			{typeof isPrimary === "boolean" && (
				<div className="flex flex-col items-end gap-1" data-no-select="true">
					{isPrimary ? (
						<>
							<label
								htmlFor={`page-primary-${section.id}`}
								className={`text-xs ${
									isPrimary ? "text-blue-100" : "text-muted-foreground"
								}`}
							>
								Your page
							</label>
							<select
								id={`page-primary-${section.id}`}
								value={primaryPage ?? ""}
								onChange={(e) =>
									onChangePrimaryPage(Number(e.target.value))
								}
								className={`w-24 rounded-md border text-sm p-1
									bg-white text-gray-900
									${isPrimary ? "border-primary" : "border-gray-300"}`}
							>
								<option value="">Page</option>
								{pageOptions.map((num) => (
									<option key={num} value={num}>
										{num}
									</option>
								))}
							</select>
						</>
					) : (
						<>
							<label
								htmlFor={`page-neighbor-${section.id}`}
								className={`text-xs text-muted-foreground`}
							>
								Neighbor page (optional)
							</label>
							<select
								id={`page-neighbor-${section.id}`}
								value={neighborPage ?? ""}
								onChange={(e) =>
									onChangeNeighborPage(Number(e.target.value))
								}
								className={`w-28 rounded-md border text-sm p-1
									bg-white text-gray-900
									${isPrimary ? "border-primary" : "border-gray-300"}`}
							>
								<option value="">Page</option>
								{pageOptions.map((num) => (
									<option key={num} value={num}>
										{num}
									</option>
								))}
							</select>
						</>
					)}
				</div>
			)}
		</div>
	);
}
