// src/features/about/About.jsx
export default function About() {
	return (
		<div className="space-y-6">
			<h1 className="text-2xl md:text-3xl font-bold">About DrawNext</h1>

			<p className="text-muted-foreground">
				DrawNext is a lightweight web app used alongside real, ring‑binder notebooks. Each
				notebook has three sections — <strong>Space</strong>, <strong>Land</strong>, and
				<strong>Ocean</strong>. Participants draw on paper, then use this app to submit a
				photo of their drawing and record where it belongs.
			</p>

			<section className="space-y-3">
				<h2 className="text-lg font-semibold">How submission works</h2>
				<ol className="list-decimal pl-5 space-y-1 text-sm">
					<li>
						<strong>Email</strong>: start by entering your email. This links your submission
						to a participant profile and helps manage uniqueness.
					</li>
					<li>
						<strong>Photo</strong>: take a picture or choose one from your gallery. The app
						automatically prepares a temporary upload for faster final submission.
					</li>
					<li>
						<strong>Notebook</strong>: select the notebook you used (if you scanned a QR
						code, it’s preselected).
					</li>
					<li>
						<strong>Section &amp; page</strong>: choose your section and its page number.
					</li>
					<li>
						<strong>Neighbors (optional)</strong>: if drawings already exist next to yours,
						record their section &amp; page numbers to connect them.
					</li>
					<li>
						<strong>Review &amp; submit</strong>: confirm details and finish.
					</li>
				</ol>
			</section>

			<section className="space-y-3">
				<h2 className="text-lg font-semibold">Why neighbors?</h2>
				<p className="text-sm text-muted-foreground">
					The notebooks are physically partitioned. Recording neighbors lets us animate the
					connections between drawings on the digital wall, showing how ideas flow across
					sections and pages.
				</p>
			</section>

			<section className="space-y-3">
				<h2 className="text-lg font-semibold">Privacy &amp; files</h2>
				<ul className="list-disc pl-5 space-y-1 text-sm">
					<li>Images are optimized (WebP) to reduce size while preserving quality.</li>
					<li>Your email is used only to associate the submission with a participant.</li>
					<li>Duplicate slots are prevented (same notebook, section, page).</li>
				</ul>
			</section>
		</div>
	);
}
