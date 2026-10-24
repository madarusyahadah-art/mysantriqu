// js/dashboard.js
const hari = Array.from({ length: 30 }, (_, i) => `${i + 1} Jul`);
const dataTren = Array.from({ length: 30 }, () => 40 + Math.round(Math.random() * 50));

new Chart(document.getElementById('tren'), {
  type: 'line',
  data: { labels: hari, datasets: [{ data: dataTren, borderColor: '#2563eb',
          backgroundColor: 'rgba(37,99,235,.08)', fill: true, tension: .35, pointRadius: 0 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('donat'), {
  type: 'doughnut',
  data: {
    labels: ['Shalat Jamaah','Tahfidz Pagi','KBM','Maktabiyah','Tahfidz Sore','Belajar Malam'],
    datasets: [{ data: [35,25,20,10,7,3],
                 backgroundColor: ['#2563eb','#3b82f6','#f59e0b','#14b8a6','#ec4899','#8b5cf6'] }]
  },
  options: { cutout: '60%', plugins: { legend: { position: 'right' } } }
});

// Peringkat (nanti ganti dengan fetch dari API)
const angkatan = [['2026',412],['2025',366],['2024',312],['2023',98],['2022',58]];
document.getElementById('angkatan').innerHTML = angkatan.map(([a, n]) =>
  `<div style="margin:8px 0">${a} <b style="float:right">${n}</b>
   <div class="bar"><span style="width:${n / 412 * 100}%"></span></div></div>`).join('');