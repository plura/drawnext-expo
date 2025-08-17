import React from 'react'
import Submission from './features/submission/Submission.jsx'
import { ConfigProvider } from './app/ConfigProvider.jsx'
import Header from './components/Header.jsx'

export default function App() {
	return (
		<ConfigProvider>
			<div className="min-h-screen bg-gray-50 flex flex-col">
				<Header />
				<main className="mx-auto w-full max-w-md flex-1 p-4">
					<Submission />
				</main>
			</div>
		</ConfigProvider>
	)
}
