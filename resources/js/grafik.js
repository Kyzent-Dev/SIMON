// Dashboard charts (DESIGN §9 theming, §7.2 chart-draw, §7.4 reduced motion). The only place for chart hex values: copies of the
// DESIGN §9/§13 tokens, because Chart.js draws on a canvas and cannot read Tailwind classes. Chart.js loads lazily, so pages
// without a chart never download it. Each component is destroyed on livewire:navigating.
export const WARNA = {
    neutral100: '#E6ECEA', neutral600: '#4B5955', neutral900: '#151C1A', borderStrong: '#7D8B87', mint700: '#17735B', putih: '#FFFFFF',
    lainnya: '#5A6B7B', // cat-other, donut "Lainnya"
};

// Irban series (DESIGN §9): colour + pointStyle + borderDash, so a series never relies on colour alone. Index = irban order by name.
const SERI_IRBAN = [
    ['#145C4A', 'circle', []], ['#C77C00', 'rect', [6, 4]], ['#6A3D9A', 'triangle', [2, 3]],
    ['#CC6677', 'rectRot', [10, 4, 2, 4]], ['#146C8F', 'crossRot', [4, 4]],
];

// Status badge fill/border (DESIGN §3.6), in the order of the grafikStatus labels: Draf, Menunggu Nomor, Perlu Revisi, Berjalan (blue), Selesai.
const WARNA_STATUS = [
    ['#E6ECEA', '#B8C4C0'], ['#FEF3C7', '#E9C46A'], ['#FFEDD5', '#FDBA74'], ['#DBEAFE', '#93C5FD'], ['#1D6B34', '#1D6B34'],
];

const reduce = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const angka = (n) => n.toLocaleString('id-ID');
// Plain copy for Chart.js: never hand it Alpine's reactive proxy.
const salin = (d) => JSON.parse(JSON.stringify(d));

let chartJs;
async function muatChart() {
    if (!chartJs) {
        chartJs = (await import('chart.js/auto')).default;
        const d = chartJs.defaults;
        d.font.family = 'Inter, system-ui, sans-serif';
        d.font.size = 12;
        d.color = WARNA.neutral600;
        d.borderColor = WARNA.neutral100;
        d.plugins.tooltip.backgroundColor = WARNA.neutral900;
        d.plugins.legend.labels.usePointStyle = true;
        d.locale = 'id-ID';
    }
    return chartJs;
}

// Value label above each bar (DESIGN §9 "A value label sits above each bar").
const labelNilai = {
    id: 'labelNilai',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        ctx.save();
        ctx.font = '600 12px Inter, system-ui, sans-serif';
        ctx.fillStyle = WARNA.neutral900;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        chart.data.datasets.forEach((ds, i) => chart.getDatasetMeta(i).data.forEach((bar, j) => ctx.fillText(angka(ds.data[j]), bar.x, bar.y - 4)));
        ctx.restore();
    },
};

/** Shared lifecycle: lazy Chart.js, first draw 600ms easeOutQuart, updates 200ms, destroy on navigation. `konfigurasi(data)` builds the config. */
const grafik = (data, konfigurasi, terapkan) => ({
    init() {
        // Latest data on the element (not reactive), so an update that lands before Chart.js loads is not lost.
        this.$el._data = data;
        this.dibuang = false;
        this.lepas = () => this.destroy();
        document.addEventListener('livewire:navigating', this.lepas, { once: true });
        muatChart().then((Chart) => {
            // destroy() may have run while Chart.js was loading: never create a chart nobody will destroy.
            if (this.dibuang || !this.$refs.kanvas?.isConnected) return;
            const config = konfigurasi(salin(this.$el._data));
            config.options.animation = reduce() ? false : { duration: 600, easing: 'easeOutQuart' };
            // Kept off the Alpine proxy: Chart.js must not be made reactive.
            this.$el._chart = new Chart(this.$refs.kanvas, config);
        });
    },
    perbarui(baru) {
        this.$el._data = baru;
        const chart = this.$el._chart;
        if (!chart) return;
        terapkan(chart, salin(baru));
        chart.options.animation = reduce() ? false : { duration: 200, easing: 'easeOutQuart' };
        chart.update();
    },
    destroy() {
        this.dibuang = true;
        document.removeEventListener('livewire:navigating', this.lepas);
        this.$el._chart?.destroy();
        this.$el._chart = null;
    },
});

/** Realisasi vs Rencana per Irban (DESIGN §9 Bar): Rencana outline (neutral-100 fill, border-strong 1.5px), Realisasi solid mint-700. */
export const grafikRealisasi = (data) => grafik(data, (d) => ({
    type: 'bar',
    data: {
        labels: d.label,
        datasets: [
            { label: 'Rencana', data: d.rencana, backgroundColor: WARNA.neutral100, borderColor: WARNA.borderStrong, borderWidth: 1.5 },
            { label: 'Realisasi', data: d.realisasi, backgroundColor: WARNA.mint700, borderColor: WARNA.mint700, borderWidth: 1.5 },
        ],
        periode: d.periode,
        persen: d.persen,
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 20 } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grace: '10%' }, x: { grid: { display: false } } },
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    title: () => '',
                    // "Irban II · TW III: 12 dari 15 (80%)"
                    label: (c) => {
                        const { data: dt } = c.chart;
                        const p = dt.persen[c.dataIndex];
                        return `${c.label} · ${dt.periode}: ${angka(dt.datasets[1].data[c.dataIndex])} dari ${angka(dt.datasets[0].data[c.dataIndex])} (${p === null ? '–' : p + '%'})`;
                    },
                },
            },
        },
    },
    plugins: [labelNilai],
}), (chart, d) => {
    chart.data.labels = d.label;
    chart.data.datasets[0].data = d.rencana;
    chart.data.datasets[1].data = d.realisasi;
    chart.data.periode = d.periode;
    chart.data.persen = d.persen;
});

/** Status SPT (irban.md mini chart): one horizontal stacked bar, a segment per status in the §3.6 badge colors; legend text carries the values. */
export const grafikStatus = (data) => grafik(data, (d) => ({
    type: 'bar',
    data: {
        labels: [`SPT ${d.tahun}`],
        datasets: d.label.map((label, i) => ({
            label, data: [d.nilai[i]], backgroundColor: WARNA_STATUS[i][0], borderColor: WARNA_STATUS[i][1], borderWidth: 1.5,
        })),
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: { x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }, y: { stacked: true, display: false } },
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    generateLabels: (chart) => chartJs.defaults.plugins.legend.labels.generateLabels(chart)
                        .map((l) => ({ ...l, text: `${l.text}: ${angka(chart.data.datasets[l.datasetIndex].data[0])}` })),
                },
            },
            tooltip: { callbacks: { title: () => '', label: (c) => `${c.dataset.label}: ${angka(c.raw)} SPT` } },
        },
    },
}), (chart, d) => {
    chart.data.labels = [`SPT ${d.tahun}`];
    chart.data.datasets.forEach((ds, i) => { ds.data = [d.nilai[i]]; });
});

/** SPT selesai per bulan (DESIGN §9 Line): one mint-700 series (width 2.5, points 3px), or one per irban with its own colour, marker and dash. */
const seriGaris = (d) => d.seri.map((s) => {
    if (s.warna === null) {
        return { label: s.nama, data: s.data, borderColor: WARNA.mint700, backgroundColor: WARNA.mint700, borderWidth: 2.5, pointRadius: 3 };
    }
    const [warna, titik, garis] = SERI_IRBAN[s.warna % SERI_IRBAN.length];
    return { label: s.nama, data: s.data, borderColor: warna, backgroundColor: warna, borderWidth: 2, borderDash: garis, pointStyle: titik, pointRadius: 4 };
});

export const grafikGaris = (data) => grafik(data, (d) => ({
    type: 'line',
    data: { labels: d.bulan, datasets: seriGaris(d) },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } },
        plugins: {
            legend: { display: d.seri.length > 1, position: 'bottom' },
            tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${angka(c.raw)} SPT` } },
        },
    },
}), (chart, d) => {
    chart.data.labels = d.bulan;
    chart.data.datasets = seriGaris(d);
    chart.options.plugins.legend.display = d.seri.length > 1;
});

const persen = (p) => `${p.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
// Slice colour: jenis hex from the database, irban index into the series colours, null = Lainnya.
const warnaIrisan = (s) => (s.warna === null ? WARNA.lainnya : typeof s.warna === 'number' ? SERI_IRBAN[s.warna % SERI_IRBAN.length][0] : s.warna);
const isiDonat = (d) => ({
    labels: d.irisan.map((s) => s.label),
    datasets: [{
        data: d.irisan.map((s) => s.jumlah), persen: d.irisan.map((s) => s.persen),
        backgroundColor: d.irisan.map(warnaIrisan), borderColor: WARNA.putih, borderWidth: 2,
    }],
});

// Centre label "128 SPT" (DESIGN §9 Donut).
const labelTengah = {
    id: 'labelTengah',
    afterDraw(chart) {
        const { ctx, chartArea: a } = chart;
        const total = chart.data.datasets[0].data.reduce((x, y) => x + y, 0);
        ctx.save();
        ctx.font = '700 20px "Plus Jakarta Sans", Inter, system-ui, sans-serif';
        ctx.fillStyle = WARNA.neutral900;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(`${angka(total)} SPT`, (a.left + a.right) / 2, (a.top + a.bottom) / 2);
        ctx.restore();
    },
};

/** Proporsi (DESIGN §9 Donut): cutout 62%, white 2px separators, ≤6 slices + Lainnya, centre total; the text legend is HTML beside it. */
export const grafikDonat = (data) => grafik(data, (d) => ({
    type: 'doughnut',
    data: isiDonat(d),
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: (c) => `${c.label}: ${angka(c.raw)} SPT (${persen(c.dataset.persen[c.dataIndex])})` } },
        },
    },
    plugins: [labelTengah],
}), (chart, d) => {
    chart.data = isiDonat(d);
});
