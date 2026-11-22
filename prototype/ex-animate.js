// 1. Perolehan suara final (data asli)
const totalSuara = {
  paslon1: 22,
  paslon2: 34,
  paslon3: 44
};

// 2. Sisa suara yang belum ditampilkan
const remaining = { ...totalSuara };

// 3. Suara yang sudah ditampilkan (display)
const display = {
  paslon1: 0,
  paslon2: 0,
  paslon3: 0
};

function randomizeVote() {
  const candidates = Object.keys(remaining)
    .filter(key => remaining[key] > 0);

  if (candidates.length === 0) return null;

  const selected = candidates[
    Math.floor(Math.random() * candidates.length)
  ];

  remaining[selected]--;
  display[selected]++;

  return {
    selected,
    display: { ...display }
  };
}