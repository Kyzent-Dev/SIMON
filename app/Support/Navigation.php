<?php

namespace App\Support;

use App\Enums\Role;
use Illuminate\Support\Facades\Route;

/** Sidebar navigation per role (design/dashboard/admin.md "Navigation groups"); one source for the sidebar and the admin quick links. */
class Navigation
{
    /** @return list<array{label: ?string, items: list<array{label: string, route: string, icon: string}>}> */
    public static function groups(Role $role): array
    {
        $item = fn (string $label, string $route, string $icon) => compact('label', 'route', 'icon');
        $dasbor = [['label' => null, 'items' => [$item('Dasbor', $role->dashboardRoute(), 'home')]]];

        $groups = $role !== Role::Admin ? $dasbor : [...$dasbor,
            ['label' => null, 'items' => [$item('Pengguna', 'admin.pengguna', 'users')]],
            ['label' => 'Master Data', 'items' => [
                $item('Pegawai', 'admin.pegawai', 'identification'),
                $item('Irban', 'admin.irban', 'building-office-2'),
                $item('Komposisi Irban Tahunan', 'admin.irban-tahunan', 'user-group'),
                $item('Tim', 'admin.tim', 'users'),
                $item('Objek Pemeriksaan', 'admin.objek', 'building-library'),
                $item('Wilayah Irban', 'admin.wilayah-irban', 'map'),
                $item('Jenis Penugasan', 'admin.jenis-penugasan', 'swatch'),
                $item('Klasifikasi & Aturan', 'admin.klasifikasi', 'tag'),
            ]],
            ['label' => null, 'items' => [$item('PKPT', 'admin.pkpt', 'calendar-days')]],
            ['label' => 'Konten Beranda', 'items' => [
                $item('Slide Beranda', 'admin.slide', 'photo'),
                $item('Kontak & Footer', 'admin.kontak', 'phone'),
            ]],
            ['label' => null, 'items' => [
                $item('Pengaturan Sistem', 'admin.pengaturan', 'cog-6-tooth'),
                $item('Impor Excel', 'admin.impor', 'arrow-up-tray'),
                $item('Log Aktivitas', 'admin.log', 'clipboard-document-list'),
            ]],
        ];

        $visible = [];
        foreach ($groups as $group) {
            $group['items'] = array_values(array_filter($group['items'], fn ($i) => Route::has($i['route'])));
            if ($group['items']) {
                $visible[] = $group;
            }
        }

        return $visible;
    }

    /** Flat list of every visible item except the role's own dashboard. */
    public static function links(Role $role): array
    {
        return array_values(array_filter(
            array_merge(...array_column(self::groups($role), 'items')),
            fn ($i) => $i['route'] !== $role->dashboardRoute(),
        ));
    }
}
