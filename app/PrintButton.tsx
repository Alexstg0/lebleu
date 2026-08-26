"use client";

export default function PrintButton() {
  return (
    <button className="pdf-btn" onClick={() => window.print()} aria-label="Exportar a PDF" title="Exportar a PDF">
      <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
        <path d="M12 16l-4-4h3V4h2v8h3l-4 4z" fill="currentColor" />
        <path d="M20 18H4v2h16v-2z" fill="currentColor" />
      </svg>
      PDF
    </button>
  );
}
