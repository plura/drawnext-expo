export default function SectionGroup({
  section,
  isPrimary,
  showField,
  primaryPage,
  neighborPage,
  maxPages = null,
  autoFocus = false,
  onSelect,
  onChangePrimaryPage,
  onChangeNeighborPage
}) {
  const label = section?.label || `Section ${section?.position}`
  const value = isPrimary ? (primaryPage ?? '') : (neighborPage ?? '')
  const handleChange = (e) =>
    isPrimary ? onChangePrimaryPage(e.target.value) : onChangeNeighborPage(e.target.value)

  return (
    <div
      className={`flex items-stretch justify-between gap-2 rounded-2xl border ${
        isPrimary ? 'ring-2 ring-black' : 'hover:bg-gray-50'
      }`}
    >
      <button
        type="button"
        onClick={onSelect}
        className="flex-1 min-w-0 p-4 text-left"
        aria-pressed={isPrimary}
      >
        <div className="text-base font-medium truncate">{label}</div>
        <p className="mt-0.5 text-xs text-gray-600">
          {isPrimary ? 'Selected section' : 'Tap to select this as your section'}
        </p>
      </button>

      {showField && (
        <div className="shrink-0 w-32 sm:w-40 p-4">
          <label className="block text-xs text-gray-600 mb-1">
            {isPrimary ? 'Your page' : 'Neighbor page'}
          </label>
          <input
            type="number"
            inputMode="numeric"
            min="1"
            max={typeof maxPages === 'number' ? maxPages : undefined}
            placeholder={typeof maxPages === 'number' ? `1–${maxPages}` : '1+'}
            value={value}
            onChange={handleChange}
            className="w-full rounded-lg border p-2 text-sm"
            autoFocus={autoFocus}
          />
        </div>
      )}
    </div>
  )
}
