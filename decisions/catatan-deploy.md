# Catatan deploy (lintas fase)

- `Cache::lock` per tahun (`irban-tahunan-{tahun}`, `irban-wilayah-{tahun}`) hanya melindungi bila cache store dipakai bersama semua proses: produksi wajib `CACHE_STORE=database` atau `redis` (bukan `array`/`file` per server). Indeks unik tetap menjadi jaring pengaman untuk Pegawai, Irban, Tim dan impor; pelanggarannya dikembalikan sebagai galat validasi, bukan 500.

