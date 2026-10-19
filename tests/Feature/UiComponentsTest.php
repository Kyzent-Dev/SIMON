<?php

namespace Tests\Feature;

use App\Enums\StatusSpt;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class UiComponentsTest extends TestCase
{
    public function test_card_variants(): void
    {
        $this->blade('<x-ui.card>Isi</x-ui.card>')->assertSee('rounded-lg', false)->assertSee('shadow-xs', false);
        $this->blade('<x-ui.card variant="mint">Isi</x-ui.card>')->assertSee('bg-mint-50', false);
        $this->blade('<x-ui.card href="/admin/pegawai">Pegawai</x-ui.card>')->assertSee('<a href="/admin/pegawai"', false)
            ->assertSee('hover:bg-mint-50', false)->assertDontSee('translate', false)->assertDontSee('transition', false);
        $this->blade('<x-ui.card href="/x" lift>X</x-ui.card>')->assertSee('hover:-translate-y-0.5', false)->assertSee('shadow-sm hover:shadow-md', false);
    }

    public function test_table_has_caption_scroll_region_and_density(): void
    {
        $view = $this->blade('<x-ui.table label="Daftar pegawai" density="compact"><x-slot:head><x-ui.th>Nama</x-ui.th><x-ui.th numeric>Jumlah</x-ui.th></x-slot:head><tr><td>A</td></tr></x-ui.table>');
        $view->assertSee('<caption class="sr-only">Daftar pegawai</caption>', false)
            ->assertSee('role="region"', false)->assertSee('tabindex="0"', false)
            ->assertSee('overflow-x-auto', false)->assertSee('tabular-nums', false)
            ->assertSee('[&_td]:h-9', false)->assertSee('scope="col"', false)->assertSee('text-right', false);
        $this->blade('<x-ui.table label="T"><x-slot:head></x-slot:head></x-ui.table>')->assertSee('[&_td]:h-12', false);
    }

    public function test_sortable_header_is_a_button_with_aria_sort(): void
    {
        $this->blade('<x-ui.th sortable direction="asc" wire:click="sortBy(\'nama\')">Nama</x-ui.th>')
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('<button type="button" wire:click="sortBy(\'nama\')"', false)
            ->assertSee('min-h-11 lg:min-h-0', false);
        $this->blade('<x-ui.th sortable>Nama</x-ui.th>')->assertSee('aria-sort="none"', false);
        $this->blade('<x-ui.th>Nama</x-ui.th>')->assertDontSee('aria-sort', false);
    }

    public function test_pagination_summary_and_per_page(): void
    {
        $p = new LengthAwarePaginator(range(1, 25), 312, 25, 1);
        $this->blade('<x-ui.pagination :paginator="$p" />', ['p' => $p])
            ->assertSee('Menampilkan 1–25 dari 312')
            ->assertSee('aria-label="Navigasi halaman"', false)
            ->assertSee('<option value="25"', false)->assertSee('<option value="50"', false)->assertSee('<option value="100"', false)
            ->assertSee('wire:model.live="perPage"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-label="Halaman berikutnya"', false);
    }

    public function test_select_and_textarea_label_error_and_required(): void
    {
        $this->blade('<x-ui.select label="Peran" name="peran" :options="[\'a\' => \'A\']" required error="Wajib diisi." helper="Pilih satu." />')
            ->assertSee('<label for="peran"', false)->assertSee('(wajib)')
            ->assertSee('aria-invalid="true"', false)->assertSee('aria-describedby="peran-helper peran-error"', false)
            ->assertSee('<option value="a"', false);
        $this->blade('<x-ui.textarea label="Catatan" name="catatan" error="Terlalu panjang." />')
            ->assertSee('<label for="catatan"', false)->assertSee('<textarea', false)
            ->assertSee('aria-describedby="catatan-error"', false);
    }

    public function test_modal_and_drawer_dialog_semantics(): void
    {
        foreach (['modal', 'drawer'] as $c) {
            $this->blade("<x-ui.$c name=\"hapus\" title=\"Hapus pegawai\" size=\"sm\">Isi</x-ui.$c>")
                ->assertSee('role="dialog"', false)->assertSee('aria-modal="true"', false)
                ->assertSee('aria-labelledby="hapus-title"', false)->assertSee('id="hapus-title"', false)
                ->assertSee('x-trap.noscroll="open"', false)->assertDontSee('.inert', false)->assertSee('x-on:keydown.escape', false)
                ->assertSee('inline-flex size-11', false)
                ->assertSee('x-modelable="open"', false)->assertSee('x-on:open-modal.window', false)
                ->assertSee('aria-label="Tutup"', false)->assertSee('duration-[var(--dur-base)]', false);
        }
        $this->blade('<x-ui.modal name="m" title="T" size="sm">x</x-ui.modal>')->assertSee('max-w-[400px]', false)->assertSee('size-11 lg:size-10', false);
        $this->blade('<x-ui.drawer name="d" title="T">x</x-ui.drawer>')->assertSee('max-w-[720px]', false);
    }

    public function test_toast_roles_and_dismissal(): void
    {
        $this->blade('<x-ui.toast />')
            ->assertSee('x-on:toast.window', false)
            ->assertSee("'alert' : null", false)->assertDontSee("'alert' : 'status'", false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('5000', false)
            ->assertSee('aria-label="Tutup notifikasi"', false);
    }

    public function test_empty_state(): void
    {
        $this->blade('<x-ui.empty-state icon="users" title="Belum ada data pegawai." description="Tambah manual atau impor dari Excel."><x-ui.button>Tambah</x-ui.button></x-ui.empty-state>')
            ->assertSee('Belum ada data pegawai.')->assertSee('size-12 text-mint-600', false)->assertSee('Tambah');
    }

    public function test_switch_and_radio(): void
    {
        $this->blade('<x-ui.switch label="Aktif" name="aktif" />')
            ->assertSee('role="switch"', false)->assertSee('type="checkbox"', false)->assertSee('<label for="aktif"', false);
        $this->blade('<x-ui.radio label="Global" name="scope" value="global" />')
            ->assertSee('type="radio"', false)->assertSee('id="scope-global"', false)->assertSee('<label for="scope-global"', false);
    }

    public function test_page_header(): void
    {
        $this->blade('<x-ui.page-header title="Pegawai" description="Data pegawai."><x-slot:actions><x-ui.button>Tambah</x-ui.button></x-slot:actions></x-ui.page-header>')
            ->assertSee('<h1', false)->assertSeeInOrder(['Pegawai', 'Data pegawai.', 'Tambah']);
    }

    public function test_status_badge_renders_icon_label_pill_for_each_status(): void
    {
        foreach (StatusSpt::cases() as $s) {
            $view = $this->blade('<x-app.status-badge :status="$s" />', ['s' => $s]);
            $view->assertSee($s->label())->assertSee('rounded-full', false)->assertSee('<svg', false)
                ->assertSee('bg-status-'.$s->value.'-bg', false)->assertSee('text-status-'.$s->value.'-fg', false);
        }
        $this->blade('<x-app.status-badge :status="$s" />', ['s' => StatusSpt::Dibatalkan])->assertSee('border-dashed', false);
        $this->blade('<x-app.status-badge :status="$s" />', ['s' => StatusSpt::Selesai])->assertDontSee('border-dashed', false);
    }

    public function test_flag_renders_icon_label_rectangle_for_each_flag(): void
    {
        $flags = ['terlambat' => 'Terlambat', 'tidak_sesuai_pkpt' => 'Tidak Sesuai PKPT', 'spt_final_belum_diupload' => 'SPT Final Belum Diupload', 'non_pkpt' => 'Non-PKPT'];
        foreach ($flags as $k => $label) {
            $this->blade('<x-app.flag :jenis="$k" />', ['k' => $k])->assertSee($label)->assertSee('<svg', false)
                ->assertSee('rounded-sm', false)->assertDontSee('rounded-full', false)->assertSee('bg-flag-'.$k.'-bg', false);
        }
    }

    public function test_segmented_is_a_radio_group_with_legend(): void
    {
        $this->blade('<x-ui.segmented name="peran-1" legend="Peran untuk Budi Santoso" :options="[\'ketua\' => \'Ketua Tim\', \'anggota\' => \'Anggota\']" value="ketua" />')
            ->assertSee('<fieldset', false)->assertSee('Peran untuk Budi Santoso')->assertSee('type="radio"', false)
            ->assertSee('Ketua Tim')->assertSee('checked', false);
    }
}
